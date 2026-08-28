<?php

namespace App\Http\Controllers;

use App\Exports\CodeRequestsExport;
use App\Models\CodeRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CodeProvidedNotification;
use App\Notifications\CodeRejectedNotification;
use App\Notifications\CodeRequestedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CodeRequestController extends Controller
{
    // ---- Employee -----------------------------------------------------------

    /** AJAX: employee fires off a quick request for a login code. */
    public function quickRequest(Request $request)
    {
        $validated = $request->validate([
            'tool_name' => 'required|string|max:100',
            'message' => 'required|string|max:255',
        ]);

        $codeRequest = CodeRequest::create([
            'employee_id' => $request->user()->id,
            'tool_name' => trim($validated['tool_name']),
            'message' => trim($validated['message']),
            'status' => 'pending',
        ]);

        // Ping every HR / super admin (bell + email).
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new CodeRequestedNotification($codeRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'request_id' => $codeRequest->id,
            'message' => 'Request sent! HR will share the code shortly.',
        ]);
    }

    /** Employee: my recent code requests (with any codes received). */
    public function myCodeRequests(Request $request)
    {
        $requests = CodeRequest::where('employee_id', $request->user()->id)
            ->with('responder')
            ->latest()
            ->take(20)
            ->get();

        return view('code-requests.my', compact('requests'));
    }

    /** AJAX (poll): the employee's own recent requests — lets a code appear on the dashboard without a refresh. */
    public function myCodeRequestsJson(Request $request)
    {
        $items = CodeRequest::where('employee_id', $request->user()->id)
            ->with('responder')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'tool' => $r->tool_name,
                'message' => $r->message,
                'status' => $r->status,
                'request_number' => $r->request_number,
                'ago' => optional($r->created_at)->diffForHumans(),
                'code' => $r->code_provided,
                'code_note' => $r->code_expires_note,
                'sent_ago' => optional($r->code_sent_at)->diffForHumans(),
                'responder' => optional($r->responder)->full_name ?? 'HR',
                'rejection_reason' => $r->rejection_reason,
                'fresh' => $r->status === 'code_sent'
                    && $r->code_sent_at
                    && $r->code_sent_at->gt(now()->subMinutes(60)),
            ]);

        return response()->json(['items' => $items]);
    }

    /** AJAX: employee cancels their own still-pending request. */
    public function cancel(Request $request, CodeRequest $codeRequest)
    {
        abort_unless($codeRequest->employee_id === $request->user()->id, 403);

        if ($codeRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request can no longer be cancelled.',
            ], 422);
        }

        $codeRequest->update(['status' => 'cancelled']);

        // Clear the "needs a login code" alert from every admin's bell.
        CodeRequestedNotification::markResolved($codeRequest->id);

        return response()->json(['success' => true]);
    }

    // ---- Admin --------------------------------------------------------------

    /** Admin: the quick-response panel. */
    public function pendingCodes(Request $request)
    {
        $this->authorizeSender($request);

        $pending = CodeRequest::pending()->with('employee')->oldest()->get();

        // Full, paginated history (no more silent 30-item cap). Optional search by
        // employee name or tool.
        $search = trim((string) $request->get('q', ''));
        $applySearch = function ($query) use ($search) {
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('tool_name', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
                });
            }
        };

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $applyDates = fn ($q) => $q
            ->when($dateFrom, fn ($qq) => $qq->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($qq) => $qq->whereDate('created_at', '<=', $dateTo));

        $sort = in_array($request->get('sort'), ['newest', 'oldest', 'employee', 'tool'], true) ? $request->get('sort') : 'newest';
        $resolved = CodeRequest::where('status', 'code_sent')
            ->with(['employee', 'responder'])
            ->tap($applySearch)->tap($applyDates)
            ->when($sort === 'oldest', fn ($q) => $q->oldest('code_sent_at'))
            ->when($sort === 'tool', fn ($q) => $q->orderBy('tool_name'))
            ->when($sort === 'employee', fn ($q) => $q->orderBy(User::select('first_name')->whereColumn('users.id', 'code_requests.employee_id')))
            ->when(!in_array($sort, ['oldest', 'tool', 'employee'], true), fn ($q) => $q->latest('code_sent_at'))
            ->paginate(20, ['*'], 'sent')
            ->withQueryString();
        $rejected = CodeRequest::where('status', 'rejected')
            ->with(['employee', 'responder'])
            ->tap($applySearch)->tap($applyDates)
            ->latest('updated_at')
            ->paginate(15, ['*'], 'declined')
            ->withQueryString();

        // Delegated code-sender management (super admin only sees the controls).
        $isSuperAdmin = $request->user()->hasRole(Role::SUPER_ADMIN);
        $senders = $isSuperAdmin
            ? User::where('can_send_codes', true)->where('account_status', '!=', 'deactivated')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email'])
            : collect();
        $grantableUsers = $isSuperAdmin
            ? User::where('account_status', '!=', 'deactivated')
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', [Role::SUPER_ADMIN, Role::HR_ADMIN]))
                ->where('can_send_codes', false)
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email'])
            : collect();

        return view('code-requests.pending', compact('pending', 'resolved', 'rejected', 'search', 'sort', 'dateFrom', 'dateTo', 'isSuperAdmin', 'senders', 'grantableUsers'));
    }

    /** Admin reveals a single stored code on demand (kept out of the page HTML). */
    public function revealCode(Request $request, CodeRequest $codeRequest)
    {
        $this->authorizeSender($request);

        return response()->json(['value' => $codeRequest->code_provided]);
    }

    /** AJAX (poll): the current pending queue, newest first — powers the live "new request pops in at the top" list. */
    public function pendingJson(Request $request)
    {
        $this->authorizeSender($request);

        $pending = CodeRequest::pending()->with('employee')->latest()->get()->map(fn ($r) => [
            'id' => $r->id,
            'employee' => optional($r->employee)->full_name ?? 'Employee',
            'tool' => $r->tool_name,
            'message' => $r->message,
            'request_number' => $r->request_number,
            'ago' => optional($r->created_at)->diffForHumans(),
        ]);

        return response()->json(['pending' => $pending]);
    }

    /** AJAX: HR sends the code back to the employee. */
    public function sendCode(Request $request, CodeRequest $codeRequest)
    {
        $this->authorizeSender($request);

        $validated = $request->validate([
            'code_provided' => 'required|string|max:500',
            'code_expires_note' => 'nullable|string|max:100',
        ]);

        $codeRequest->update([
            'code_provided' => trim($validated['code_provided']),
            'code_expires_note' => $validated['code_expires_note'] ?? null,
            'status' => 'code_sent',
            'code_sent_at' => now(),
            'responded_by' => $request->user()->id,
        ]);

        // Work done — clear the "needs a login code" alert for all admins.
        CodeRequestedNotification::markResolved($codeRequest->id);

        if ($codeRequest->employee) {
            try {
                $codeRequest->employee->notify(new CodeProvidedNotification($codeRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Sent to ' . (optional($codeRequest->employee)->full_name ?? 'employee') . '.',
        ]);
    }

    /** AJAX: HR declines the request (optionally with a reason). */
    public function rejectCode(Request $request, CodeRequest $codeRequest)
    {
        $this->authorizeSender($request);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ], [
            'rejection_reason.required' => 'Please give the employee a brief reason for declining.',
        ]);

        $codeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => trim($validated['rejection_reason']),
            'responded_by' => $request->user()->id,
        ]);

        // Work done — clear the "needs a login code" alert for all admins.
        CodeRequestedNotification::markResolved($codeRequest->id);

        if ($codeRequest->employee) {
            try {
                $codeRequest->employee->notify(new CodeRejectedNotification($codeRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Declined ' . (optional($codeRequest->employee)->full_name ?? 'the request') . '.',
        ]);
    }

    /** Re-notify the employee with the code that was already sent (if not yet purged). */
    public function resendCode(Request $request, CodeRequest $codeRequest)
    {
        $this->authorizeSender($request);

        if (!$codeRequest->hasCode()) {
            return response()->json(['success' => false, 'message' => 'That code was cleared for security and can no longer be resent — send a new one.'], 422);
        }

        if ($codeRequest->employee) {
            try {
                $codeRequest->employee->notify(new CodeProvidedNotification($codeRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Resent to ' . (optional($codeRequest->employee)->full_name ?? 'employee') . '.',
        ]);
    }

    /** Edit the decline reason on an already-declined request. */
    public function updateRejection(Request $request, CodeRequest $codeRequest)
    {
        $this->authorizeSender($request);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ], [
            'rejection_reason.required' => 'A reason is required.',
        ]);

        $codeRequest->update(['rejection_reason' => trim($validated['rejection_reason'])]);

        return response()->json(['success' => true, 'reason' => $codeRequest->rejection_reason]);
    }

    // ---- Delegated senders (super admin grants code-send access) ------------

    public function grantSender(Request $request, User $user)
    {
        abort_unless($request->user() && $request->user()->hasRole(Role::SUPER_ADMIN), 403, 'Only a super admin can grant code-send access.');

        $user->forceFill(['can_send_codes' => true])->save();

        return back()->with('success', $user->full_name . ' can now send codes.');
    }

    public function revokeSender(Request $request, User $user)
    {
        abort_unless($request->user() && $request->user()->hasRole(Role::SUPER_ADMIN), 403, 'Only a super admin can revoke code-send access.');

        $user->forceFill(['can_send_codes' => false])->save();

        return back()->with('success', $user->full_name . ' can no longer send codes.');
    }

    // ---- Report download (Excel / PDF) -------------------------------------

    /**
     * Report dataset for exports — every request in the chosen date range +
     * optional status + search. The code VALUES are never exported (they are
     * meant to be redacted after use).
     */
    private function reportQuery(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        return CodeRequest::with(['employee.department', 'responder'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('tool_name', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            }))
            ->latest('created_at');
    }

    private function reportStem(Request $request): string
    {
        $from = $request->get('date_from');
        $to = $request->get('date_to');
        $range = ($from || $to) ? '-' . ($from ?: 'start') . '_to_' . ($to ?: 'now') : '';

        return 'code-requests' . $range;
    }

    public function export(Request $request)
    {
        $this->authorizeSender($request);

        return Excel::download(new CodeRequestsExport($this->reportQuery($request)->get()), $this->reportStem($request) . '.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeSender($request);

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $pdf = Pdf::loadView('code-requests.pdf', [
            'requests' => $this->reportQuery($request)->get(),
            'dateFrom' => $request->get('date_from'),
            'dateTo' => $request->get('date_to'),
            'status' => $request->get('status', 'all'),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $this->reportStem($request) . '.pdf"',
        ]);
    }

    // ------------------------------------------------------------------------

    /** Admins, or a user a super admin has granted code-send access. */
    private function authorizeSender(Request $request): void
    {
        $u = $request->user();
        abort_unless($u && ($u->isAdmin() || $u->can_send_codes), 403, 'You do not have access to code requests.');
    }
}
