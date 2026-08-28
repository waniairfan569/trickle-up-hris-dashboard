<?php

namespace App\Http\Controllers;

use App\Exports\EquipmentRequestsExport;
use App\Models\EquipmentRequest;
use App\Models\User;
use App\Notifications\EquipmentRequestedNotification;
use App\Notifications\EquipmentRequestReviewedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class EquipmentRequestController extends Controller
{
    // ---- Employee -----------------------------------------------------------

    /** Employee: submit form + my recent equipment requests. */
    public function index(Request $request)
    {
        $requests = EquipmentRequest::where('user_id', $request->user()->id)
            ->with('reviewer')
            ->latest()
            ->take(30)
            ->get();

        return view('equipment.index', compact('requests'));
    }

    /** Employee: submit a request to take equipment home. */
    public function store(Request $request)
    {
        $items = config('equipment.items', []);

        $validated = $request->validate([
            'equipment_type' => 'required|string|max:100',
            'equipment_details' => 'nullable|string|max:150',
            'reason' => 'required|string|max:1000',
            'expected_return_date' => 'nullable|date|after_or_equal:today',
        ], [
            'equipment_type.required' => 'Please choose which equipment you want to take home.',
            'reason.required' => 'Please explain why you need to take it home.',
        ]);

        $type = trim($validated['equipment_type']);
        $details = trim((string) ($validated['equipment_details'] ?? ''));

        // A listed item may carry an optional model/detail; "Other" (or anything
        // off-list) must be spelled out. Build one tidy, consistent name.
        $isOther = strcasecmp($type, 'Other') === 0 || !in_array($type, $items, true);
        if ($isOther && $details === '') {
            return back()->withInput()->withErrors(['equipment_details' => 'Please name the equipment you want to take home.']);
        }
        $equipmentName = $isOther
            ? $details
            : ($details !== '' ? "{$type} — {$details}" : $type);

        // Guard against accidental double-submits: the same item already pending
        // for this employee within the last few minutes.
        $dupe = EquipmentRequest::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->where('equipment_name', $equipmentName)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();
        if ($dupe) {
            return back()->with('success', 'You already have that request pending — no need to send it again.');
        }

        $equipmentRequest = EquipmentRequest::create([
            'user_id' => $request->user()->id,
            'equipment_name' => $equipmentName,
            'reason' => trim($validated['reason']),
            'expected_return_date' => $validated['expected_return_date'] ?? null,
            'status' => 'pending',
        ]);

        // Notify every HR / super admin (bell + email).
        foreach ($this->admins() as $admin) {
            try {
                $admin->notify(new EquipmentRequestedNotification($equipmentRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Request sent to admin. You’ll be notified once it’s reviewed.');
    }

    // ---- Admin --------------------------------------------------------------

    /** Admin: review queue + decided history. */
    public function adminIndex(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        $pending = EquipmentRequest::pending()->with('employee')->oldest()->get();

        // Full, paginated decision history (no more silent 50-item cap). Optional
        // search by employee name or equipment.
        $search = trim((string) $request->get('q', ''));
        $applySearch = function ($query) use ($search) {
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('equipment_name', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
                });
            }
        };

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $sort = in_array($request->get('sort'), ['newest', 'oldest', 'employee', 'equipment'], true) ? $request->get('sort') : 'newest';
        $decided = EquipmentRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['employee', 'reviewer'])
            ->tap($applySearch)
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($sort === 'oldest', fn ($q) => $q->oldest('reviewed_at'))
            ->when($sort === 'equipment', fn ($q) => $q->orderBy('equipment_name'))
            ->when($sort === 'employee', fn ($q) => $q->orderBy(User::select('first_name')->whereColumn('users.id', 'equipment_requests.user_id')))
            ->when(!in_array($sort, ['oldest', 'equipment', 'employee'], true), fn ($q) => $q->latest('reviewed_at'))
            ->paginate(20, ['*'], 'decided')
            ->withQueryString();

        return view('equipment.admin', compact('pending', 'decided', 'search', 'sort', 'dateFrom', 'dateTo'));
    }

    /**
     * The report dataset for exports: every request in the chosen date range
     * (by request date) + optional status + search — pending and decided.
     */
    private function reportQuery(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        return EquipmentRequest::with(['employee.department', 'reviewer'])
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('equipment_name', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($e) => $e->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            }))
            ->latest('created_at');
    }

    private function reportStem(Request $request): string
    {
        $from = $request->get('date_from');
        $to = $request->get('date_to');
        $range = ($from || $to) ? '-' . ($from ?: 'start') . '_to_' . ($to ?: 'now') : '';

        return 'equipment-requests' . $range;
    }

    /** Admin: download the requests (date range / status / search) as Excel. */
    public function export(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        return Excel::download(new EquipmentRequestsExport($this->reportQuery($request)->get()), $this->reportStem($request) . '.xlsx');
    }

    /** Admin: download (or ?preview=1 to open inline) the requests as a PDF. */
    public function exportPdf(Request $request)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $requests = $this->reportQuery($request)->get();

        $pdf = Pdf::loadView('equipment.pdf', [
            'requests' => $requests,
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

    /** Admin: approve a request. */
    public function approve(Request $request, EquipmentRequest $equipmentRequest)
    {
        return $this->decide($request, $equipmentRequest, 'approved');
    }

    /** Admin: reject a request. */
    public function reject(Request $request, EquipmentRequest $equipmentRequest)
    {
        return $this->decide($request, $equipmentRequest, 'rejected');
    }

    private function decide(Request $request, EquipmentRequest $equipmentRequest, string $status)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        // A decline must always carry a reason for the employee; a note on an
        // approval stays optional.
        $validated = $request->validate([
            'review_note' => ($status === 'rejected' ? 'required' : 'nullable') . '|string|max:255',
        ], [
            'review_note.required' => 'Please give the employee a brief reason for declining.',
        ]);

        $equipmentRequest->update([
            'status' => $status,
            'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Work done — clear this request's alert for all admins.
        EquipmentRequestedNotification::markResolved($equipmentRequest->id);

        if ($equipmentRequest->employee) {
            try {
                $equipmentRequest->employee->notify(new EquipmentRequestReviewedNotification($equipmentRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $verb = $status === 'approved' ? 'approved' : 'declined';

        return back()->with('success', "Request {$verb} — " . (optional($equipmentRequest->employee)->full_name ?? 'employee') . ' has been notified.');
    }

    /** Admin: permanently remove a request (e.g. test data or a duplicate). */
    public function destroy(Request $request, EquipmentRequest $equipmentRequest)
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403);

        // If it was still pending, clear its "needs review" alert from the bells.
        if ($equipmentRequest->status === 'pending') {
            EquipmentRequestedNotification::markResolved($equipmentRequest->id);
        }

        $equipmentRequest->delete();

        return back()->with('success', 'Request deleted.');
    }

    /** Active HR / super admins to notify. */
    private function admins()
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();
    }
}
