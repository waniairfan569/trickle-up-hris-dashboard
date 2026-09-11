<?php

namespace App\Http\Controllers;

use App\Models\CompensationClaim;
use App\Models\TimeOffPolicy;
use App\Models\User;
use App\Notifications\CompensationClaimReviewed;
use App\Notifications\CompensationClaimSubmitted;
use App\Services\TimeOffBalanceService;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compensation leave claims: an employee asks for time off in lieu of overtime
 * they worked; HR / super admin approves (crediting the compensatory policy
 * balance) or declines. Complements the direct "Credit Comp Leave" action.
 */
class CompensationClaimController extends Controller
{
    use LogsActivity;

    public function __construct(private TimeOffBalanceService $balanceService) {}

    /** Employee: claim comp leave for an overtime day. */
    public function store(Request $request)
    {
        $user = $request->user();

        $policy = TimeOffPolicy::active()->where('type', 'compensatory')->orderBy('id')->first();
        if (! $policy) {
            return back()->with('error', 'Compensation leave is not set up for this workspace yet — ask HR to add a compensatory leave policy.');
        }

        $validated = $request->validate([
            'worked_date' => 'required|date|before_or_equal:today',
            'hours_worked' => 'nullable|numeric|min:0.5|max:24',
            'days_claimed' => 'required|numeric|min:0.5|max:10',
            'reason' => 'required|string|max:500',
        ], [
            'worked_date.before_or_equal' => 'The overtime date can’t be in the future.',
            'reason.required' => 'Tell HR what overtime you worked (e.g. "Covered the Saturday shift").',
        ]);

        $days = round(((float) $validated['days_claimed']) * 2) / 2; // half-day steps
        if ($days <= 0) {
            return back()->withErrors(['days_claimed' => 'Claim at least half a day.'])->withInput();
        }

        $workedDate = Carbon::parse($validated['worked_date'])->toDateString();
        $duplicate = CompensationClaim::where('user_id', $user->id)
            ->where('worked_date', $workedDate)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        if ($duplicate) {
            return back()->with('error', 'You already have a claim for overtime on ' . Carbon::parse($workedDate)->format('d M Y') . '.')->withInput();
        }

        $claim = CompensationClaim::create([
            'user_id' => $user->id,
            'worked_date' => $workedDate,
            'hours_worked' => $validated['hours_worked'] ?? null,
            'days_claimed' => $days,
            'reason' => trim($validated['reason']),
            'status' => 'pending',
        ]);

        foreach ($this->approvers() as $admin) {
            try {
                $admin->notify(new CompensationClaimSubmitted($claim));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', "Compensation leave claim for {$days} day(s) sent to HR for approval.");
    }

    /** Employee: withdraw a claim that hasn't been decided yet. */
    public function cancel(Request $request, CompensationClaim $claim)
    {
        abort_unless($claim->user_id === $request->user()->id, 403);

        if ($claim->status !== 'pending') {
            return back()->with('error', 'This claim has already been decided.');
        }

        $claim->update(['status' => 'cancelled']);

        return back()->with('success', 'Compensation leave claim withdrawn.');
    }

    /** HR / super admin: approve — credits the comp balance (optionally a different number of days). */
    public function approve(Request $request, CompensationClaim $claim)
    {
        $admin = $request->user();
        abort_unless($this->canDecide($admin), 403);

        if ($claim->status !== 'pending') {
            return back()->with('error', 'This claim has already been decided.');
        }

        $validated = $request->validate([
            'days_credited' => 'nullable|numeric|min:0.5|max:10',
            'review_note' => 'nullable|string|max:500',
        ]);
        $days = round(((float) ($validated['days_credited'] ?? $claim->days_claimed)) * 2) / 2;
        if ($days <= 0) {
            return back()->with('error', 'Credit at least half a day, or decline the claim instead.');
        }

        $policy = TimeOffPolicy::active()->where('type', 'compensatory')->orderBy('id')->first();
        if (! $policy) {
            return back()->with('error', 'No active compensatory leave policy to credit — add one under Time-off policies first.');
        }

        $employee = $claim->employee;
        abort_unless($employee, 404);

        $note = 'Compensation claim #' . $claim->id . ' — overtime on ' . $claim->worked_date->format('d M Y')
            . ($claim->hours_worked ? " ({$claim->hours_worked}h)" : '') . ': ' . $claim->reason;

        DB::transaction(function () use ($claim, $employee, $policy, $days, $note, $admin, $validated) {
            $this->balanceService->manualAdjust($employee, $policy, $days, $note, $admin);

            $claim->update([
                'days_credited' => $days,
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);
        });

        try {
            $this->logActivity(
                'adjusted',
                'TimeOffBalance',
                $employee->id,
                "Approved {$employee->full_name}'s compensation leave claim: +{$days} day(s) — {$note}",
                ['policy_id' => $policy->id, 'amount' => $days, 'compensation_claim_id' => $claim->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $employee->notify(new CompensationClaimReviewed($claim->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }

        $diff = abs($days - (float) $claim->days_claimed) > 0.01 ? ' (claimed ' . (float) $claim->days_claimed . ')' : '';

        return back()->with('success', "Claim approved — {$days} day(s) of {$policy->name} credited to {$employee->first_name}{$diff}.");
    }

    /** HR / super admin: decline the claim; nothing is credited. */
    public function reject(Request $request, CompensationClaim $claim)
    {
        $admin = $request->user();
        abort_unless($this->canDecide($admin), 403);

        if ($claim->status !== 'pending') {
            return back()->with('error', 'This claim has already been decided.');
        }

        $validated = $request->validate(['review_note' => 'nullable|string|max:500']);

        $claim->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        try {
            $claim->employee?->notify(new CompensationClaimReviewed($claim->fresh()));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Compensation leave claim declined.');
    }

    private function canDecide(User $user): bool
    {
        return $user->hasRole('hr_admin') || $user->hasRole('super_admin');
    }

    private function approvers()
    {
        return User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
              ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();
    }
}
