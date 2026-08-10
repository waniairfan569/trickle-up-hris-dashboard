<?php

namespace App\Http\Controllers;

use App\Models\CodeRequest;
use App\Models\User;
use App\Notifications\CodeProvidedNotification;
use App\Notifications\CodeRejectedNotification;
use App\Notifications\CodeRequestedNotification;
use Illuminate\Http\Request;

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
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

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

        $resolved = CodeRequest::where('status', 'code_sent')
            ->with(['employee', 'responder'])
            ->tap($applySearch)
            ->latest('code_sent_at')
            ->paginate(20, ['*'], 'sent')
            ->withQueryString();
        $rejected = CodeRequest::where('status', 'rejected')
            ->with(['employee', 'responder'])
            ->tap($applySearch)
            ->latest('updated_at')
            ->paginate(15, ['*'], 'declined')
            ->withQueryString();

        return view('code-requests.pending', compact('pending', 'resolved', 'rejected', 'search'));
    }

    /** Admin reveals a single stored code on demand (kept out of the page HTML). */
    public function revealCode(Request $request, CodeRequest $codeRequest)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        return response()->json(['value' => $codeRequest->code_provided]);
    }

    /** AJAX (poll): the current pending queue, newest first — powers the live "new request pops in at the top" list. */
    public function pendingJson(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

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
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'code_provided' => 'required|string|max:100',
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
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        $codeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => trim((string) ($validated['rejection_reason'] ?? '')) ?: null,
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
}
