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
        $validated = $request->validate([
            'equipment_name' => 'required|string|max:150',
            'reason' => 'required|string|max:1000',
            'expected_return_date' => 'nullable|date|after_or_equal:today',
        ], [
            'equipment_name.required' => 'Please name the equipment you want to take home.',
            'reason.required' => 'Please explain why you need to take it home.',
        ]);

        $equipmentRequest = EquipmentRequest::create([
            'user_id' => $request->user()->id,
            'equipment_name' => trim($validated['equipment_name']),
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
        $decided = EquipmentRequest::whereIn('status', ['approved', 'rejected'])
            ->with(['employee', 'reviewer'])
            ->latest('reviewed_at')
            ->take(50)
            ->get();

        return view('equipment.admin', compact('pending', 'decided'));
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

        $validated = $request->validate([
            'review_note' => 'nullable|string|max:255',
        ]);

        $equipmentRequest->update([
            'status' => $status,
            'review_note' => trim((string) ($validated['review_note'] ?? '')) ?: null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

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

    /** Active HR / super admins to notify. */
    private function admins()
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();
    }
}
