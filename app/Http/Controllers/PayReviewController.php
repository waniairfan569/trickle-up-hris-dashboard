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

        return redirect()->route('employees.profile', $employee->id)
            ->with('success', 'Pay review recorded. The employee has been notified.');
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

    /** Set the employee's salary column to the currently-active review's amount. */
    private function syncCurrentSalary(User $employee): void
    {
        $active = $employee->payReviews()
            ->where('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->first();

        if ($active) {
            $employee->forceFill([
                'salary' => $active->new_salary,
                'salary_currency' => $active->currency,
            ])->save();
        }
    }

    private function authorizeManage(): void
    {
        $auth = auth()->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Only HR administrators can record pay reviews.');
    }
}
