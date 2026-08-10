<?php

namespace App\Http\Controllers;

use App\Models\EquipmentRequest;
use App\Models\User;
use App\Notifications\EquipmentRequestedNotification;
use App\Notifications\EquipmentRequestReviewedNotification;
use Illuminate\Http\Request;

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

        $sort = in_array($request->get('sort'), ['newest', 'oldest', 'employee', 'equipment'], true) ? $request->get('sort') : 'newest';
        $decided = EquipmentRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['employee', 'reviewer'])
            ->tap($applySearch)
            ->when($sort === 'oldest', fn ($q) => $q->oldest('reviewed_at'))
            ->when($sort === 'equipment', fn ($q) => $q->orderBy('equipment_name'))
            ->when($sort === 'employee', fn ($q) => $q->orderBy(User::select('first_name')->whereColumn('users.id', 'equipment_requests.user_id')))
            ->when(!in_array($sort, ['oldest', 'equipment', 'employee'], true), fn ($q) => $q->latest('reviewed_at'))
            ->paginate(20, ['*'], 'decided')
            ->withQueryString();

        return view('equipment.admin', compact('pending', 'decided', 'search', 'sort'));
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
