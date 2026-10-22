<?php

namespace App\Http\Controllers;

use App\Models\PayReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayReviewController extends Controller
{
    /** Record a new pay review for an employee (admin only). */
    public function store(Request $request, User $employee)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'effective_date' => 'required|date',
            'new_salary' => 'required|numeric|min:0',
            'pay_type' => ['required', Rule::in(['salary', 'hourly', 'contract'])],
            'pay_schedule' => ['required', Rule::in(['monthly', 'weekly', 'biweekly', 'annual', 'hourly'])],
            'review_type' => ['required', Rule::in(['annual', 'mid_year', 'promotion', 'correction', 'market_adjustment', 'joining'])],
            // A promotion is usually a new title as well as new money. Recording
            // both here keeps it one action, and lets the letter print the title
            // the person is moving to rather than the one they are leaving.
            'job_title' => 'nullable|string|max:255',
            'reason' => 'required|string|max:255',
            'approved_by' => 'nullable|integer|exists:users,id',
            'note' => 'nullable|string|max:1000',
            'currency' => 'nullable|string|max:8',
        ]);

        $effective = Carbon::parse($validated['effective_date'])->startOfDay();

        // Previous salary = the most recent prior review (by effective date), else the
        // employee's current salary on record.
        $prior = $employee->payReviews()
            ->where('effective_date', '<=', $effective->toDateString())
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->first();

        $previousSalary = $prior ? (float) $prior->new_salary : ($employee->salary !== null ? (float) $employee->salary : null);
        $newSalary = (float) $validated['new_salary'];

        $increment = $previousSalary !== null ? round($newSalary - $previousSalary, 2) : 0;
        $percent = ($previousSalary && $previousSalary > 0) ? round(($increment / $previousSalary) * 100, 2) : null;
        $monthsSince = $prior ? $prior->effective_date->diffInMonths($effective) : null;

        // The title move, if one came with this review. Blank, or the same title
        // they already hold, counts as no move at all.
        $previousTitle = (string) ($employee->job_title ?? '');
        $newTitle = trim((string) ($validated['job_title'] ?? ''));
        $titleChanged = $newTitle !== '' && $newTitle !== $previousTitle;

        $review = $employee->payReviews()->create([
            'effective_date' => $effective->toDateString(),
            'previous_salary' => $previousSalary,
            'new_salary' => $newSalary,
            'increment_amount' => $increment,
            'increment_percent' => $percent,
            'months_since_last' => $monthsSince,
            'currency' => $validated['currency'] ?? ($employee->salary_currency ?: 'PKR'),
            'pay_type' => $validated['pay_type'],
            'pay_schedule' => $validated['pay_schedule'],
            'review_type' => $validated['review_type'],
            'previous_job_title' => $titleChanged ? $previousTitle : null,
            'new_job_title' => $titleChanged ? $newTitle : null,
            'reason' => $validated['reason'],
            'approved_by' => $validated['approved_by'] ?? null,
            'note' => $validated['note'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Keep the employee's current salary in sync with the active review.
        $this->syncCurrentSalary($employee);

        // Let the employee know (in-app + email). Never let a mail hiccup fail the save.
        try {
            $employee->notify(new \App\Notifications\PayReviewRecorded($review));
        } catch (\Throwable $e) {
            report($e);
        }

        // Draft the letter this change calls for, if the workspace has assigned
        // a template to it. It is only a draft — nothing reaches the employee
        // until someone reads it and sends it.
        $drafted = null;
        try {
            $drafted = app(\App\Services\HrDocumentAutoGenerator::class)
                ->generateForEvent('pay_change', $employee, $effective, auth()->id());
        } catch (\Throwable $e) {
            report($e);
        }

        $what = $titleChanged ? 'Pay review and job title recorded' : 'Pay review recorded';

        // Say when the title has not taken effect yet, so nobody reads the old
        // one on the profile as the change having failed.
        $pending = $titleChanged && $effective->gt(now()->startOfDay())
            ? " The new title applies from {$effective->format('j M Y')}."
            : '';

        return redirect()->route('employees.profile', $employee->id)
            ->with('success', $drafted
                ? "{$what} and the employee notified.{$pending} A letter has been drafted for you to review and send."
                : "{$what}. The employee has been notified.{$pending}");
    }

    /** Delete a review — only future-dated (Upcoming) records may be removed. */
    public function destroy(User $employee, PayReview $payReview)
    {
        $this->authorizeManage();
        abort_unless($payReview->user_id === $employee->id, 404);

        if ($payReview->effective_date->lte(now()->startOfDay())) {
            return back()->withErrors(['pay_review' => 'Active and historical pay reviews cannot be deleted.']);
        }

        $payReview->delete();
        $this->syncCurrentSalary($employee);

        return back()->with('success', 'Upcoming pay review removed.');
    }

    /**
     * Set the employee's salary — and job title, where the review carried one —
     * from whichever review is in force today.
     *
     * The title follows the same rule as the money on purpose: a promotion
     * dated next month should not retitle somebody this afternoon. Until that
     * date arrives the new title lives on the review, which is where the letter
     * reads it from.
     */
    private function syncCurrentSalary(User $employee): void
    {
        $active = $employee->payReviews()
            ->where('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->first();

        if (! $active) {
            return;
        }

        $changes = [
            'salary' => $active->new_salary,
            'salary_currency' => $active->currency,
        ];

        if (trim((string) $active->new_job_title) !== '') {
            $changes['job_title'] = $active->new_job_title;
        }

        $employee->forceFill($changes)->save();
    }

    private function authorizeManage(): void
    {
        $auth = auth()->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Only HR administrators can record pay reviews.');
    }
}
