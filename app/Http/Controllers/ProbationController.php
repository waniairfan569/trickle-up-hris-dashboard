<?php

namespace App\Http\Controllers;

use App\Models\Probation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProbationController extends Controller
{
    /** List every employee currently on probation. */
    public function index()
    {
        $this->authorizeManage();

        $probations = Probation::with('employee.department')
            ->where('status', 'active')
            ->whereHas('employee', fn ($q) => $q->where('account_status', '!=', 'deactivated'))
            ->orderBy('end_date')
            ->get()
            ->filter(fn ($p) => $p->employee)
            ->values();

        $overdue = $probations->filter(fn ($p) => $p->end_date->lt(now()->startOfDay()))->count();

        return view('probation.index', compact('probations', 'overdue'));
    }

    /** Start a 3-month probation (defaults from hire date). */
    public function store(Request $request, User $employee)
    {
        $this->authorizeManage();

        if ($employee->probations()->where('status', 'active')->exists()) {
            return back()->withErrors(['probation' => 'This employee already has an active probation.']);
        }

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'note' => 'nullable|string|max:1000',
        ]);

        $start = $validated['start_date'] ?? optional($employee->hire_date)->toDateString() ?? now()->toDateString();
        $start = Carbon::parse($start);
        $end = !empty($validated['end_date']) ? Carbon::parse($validated['end_date']) : $start->copy()->addMonths(3);

        $probation = $employee->probations()->create([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'original_end_date' => $end->toDateString(),
            'status' => 'active',
            'note' => $validated['note'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $probation->logEvent('started', $validated['note'] ?? null);

        return back()->with('success', 'Probation started.');
    }

    /** Extend the probation end date (performance-based), with a reason. */
    public function extend(Request $request, User $employee, Probation $probation)
    {
        $this->authorizeManage();
        $this->ensureOwned($employee, $probation);

        $validated = $request->validate([
            'new_end_date' => 'required|date|after:' . $probation->end_date->toDateString(),
            'reason' => 'required|string|max:1000',
        ]);

        if (!$probation->original_end_date) {
            $probation->original_end_date = $probation->end_date;
        }
        $probation->end_date = Carbon::parse($validated['new_end_date'])->toDateString();
        $probation->status = 'active';
        $probation->save();
        $probation->logEvent('extended', $validated['reason'], $probation->end_date);

        return back()->with('success', 'Probation extended.');
    }

    /** Confirm the employee (probation passed). */
    public function confirm(Request $request, User $employee, Probation $probation)
    {
        $this->authorizeManage();
        $this->ensureOwned($employee, $probation);

        $probation->update(['status' => 'passed']);
        $probation->logEvent('passed', $request->input('note'));

        return back()->with('success', 'Probation confirmed — employee passed.');
    }

    /** Fail the probation, with a reason. */
    public function fail(Request $request, User $employee, Probation $probation)
    {
        $this->authorizeManage();
        $this->ensureOwned($employee, $probation);

        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $probation->update(['status' => 'failed']);
        $probation->logEvent('failed', $validated['reason']);

        return back()->with('success', 'Probation marked as not confirmed.');
    }

    /** Update the running note. */
    public function note(Request $request, User $employee, Probation $probation)
    {
        $this->authorizeManage();
        $this->ensureOwned($employee, $probation);

        $validated = $request->validate(['note' => 'nullable|string|max:1000']);
        $probation->update(['note' => $validated['note'] ?? null]);
        $probation->logEvent('note', $validated['note'] ?? null);

        return back()->with('success', 'Probation note updated.');
    }

    /** Remove the probation record entirely (reset). */
    public function destroy(User $employee, Probation $probation)
    {
        $this->authorizeManage();
        $this->ensureOwned($employee, $probation);

        $probation->delete();

        return back()->with('success', 'Probation record removed.');
    }

    private function ensureOwned(User $employee, Probation $probation): void
    {
        abort_unless($probation->user_id === $employee->id, 404);
    }

    private function authorizeManage(): void
    {
        $auth = auth()->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Only HR administrators can manage probation.');
    }
}
