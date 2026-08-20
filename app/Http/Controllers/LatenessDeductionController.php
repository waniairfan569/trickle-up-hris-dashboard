<?php

namespace App\Http\Controllers;

use App\Models\LatenessDeduction;
use App\Models\User;
use App\Services\LatenessDeductionService;
use Illuminate\Http\Request;

class LatenessDeductionController extends Controller
{
    public function __construct(private LatenessDeductionService $service) {}

    /** Employee (or admin on their behalf) appeals a penalty with a reason. */
    public function requestReversal(Request $request, LatenessDeduction $deduction)
    {
        abort_unless(auth()->id() === (int) $deduction->user_id || $this->isAdmin(), 403);

        $request->validate(['reason' => 'required|string|max:1000']);

        if ($deduction->reverted_at || (float) $deduction->days_deducted <= 0) {
            return back()->with('error', 'There is no active penalty to reverse for this month.');
        }

        $deduction->update([
            'reversal_status'       => 'requested',
            'reversal_reason'       => $request->reason,
            'reversal_requested_at' => now(),
            'reversal_response'     => null,
            'reversal_reviewed_at'  => null,
            'reversal_reviewed_by'  => null,
        ]);

        // Notify HR / super admins (best-effort).
        try {
            $admins = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))->get();
            foreach ($admins as $admin) {
                $admin->notify(new \App\Notifications\LatenessReversalRequested($deduction));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Reversal request submitted for review.');
    }

    /** Admin reverts a penalty directly (no request needed). */
    public function revert(Request $request, LatenessDeduction $deduction)
    {
        $admin = $this->adminOr403();

        if ($deduction->reverted_at) {
            return back()->with('error', 'This penalty has already been reverted.');
        }

        $this->service->revert($deduction, $admin, $request->input('response'));
        $this->notifyEmployee($deduction, 'approved');

        return back()->with('success', 'Penalty reverted — the deducted days were restored.');
    }

    /** Admin approves (reverts) or declines a reversal request, with a response. */
    public function reviewReversal(Request $request, LatenessDeduction $deduction)
    {
        $admin = $this->adminOr403();

        $request->validate([
            'action'   => 'required|in:approve,reject',
            'response' => 'nullable|string|max:1000',
        ]);

        if ($request->action === 'approve') {
            $this->service->revert($deduction, $admin, $request->response);
            $this->notifyEmployee($deduction, 'approved');

            return back()->with('success', 'Reversal approved — penalty reverted and days restored.');
        }

        $deduction->update([
            'reversal_status'      => 'rejected',
            'reversal_response'    => $request->response,
            'reversal_reviewed_at' => now(),
            'reversal_reviewed_by' => $admin->id,
        ]);
        $this->notifyEmployee($deduction, 'rejected');

        return back()->with('success', 'Reversal request declined.');
    }

    private function notifyEmployee(LatenessDeduction $deduction, string $outcome): void
    {
        try {
            optional($deduction->employee)->notify(new \App\Notifications\LatenessReversalReviewed($deduction, $outcome));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function isAdmin(): bool
    {
        $u = auth()->user();

        return $u && ($u->hasRole('super_admin') || $u->hasRole('hr_admin'));
    }

    private function adminOr403(): User
    {
        abort_unless($this->isAdmin(), 403);

        return auth()->user();
    }
}
