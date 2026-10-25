<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\LeaveRenewalService;
use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * What a leaver has earned by their last day, and writing it to their balance.
 *
 * The same work the leave:settlement command does, from the browser — because
 * the person who needs it is in HR, not in a terminal, and a figure nobody can
 * reach is barely better than one nobody has worked out.
 *
 * Settlement spans every policy somebody holds, so it belongs to the employee
 * rather than to one policy's balances page.
 */
class LeaveSettlementController extends Controller
{
    use LogsActivity;

    public function show(Request $request, User $employee)
    {
        $this->authorizeSettle();

        $leaving = $this->leavingDate($request, $employee);

        return view('leave-settlement.show', [
            'employee' => $employee,
            'leaving'  => $leaving,
            'rows'     => $leaving ? app(LeaveRenewalService::class)->settlementFor($employee, $leaving) : [],
            'recorded' => Employee::where('user_id', $employee->id)->value('termination_date'),
        ]);
    }

    public function apply(Request $request, User $employee)
    {
        $this->authorizeSettle();

        $validated = $request->validate([
            'leaving_date' => 'required|date',
            'again' => 'nullable|boolean',
        ]);

        $leaving = Carbon::parse($validated['leaving_date'])->startOfDay();

        $applied = app(LeaveRenewalService::class)->applySettlement(
            $employee, $leaving, $request->user(), $request->boolean('again')
        );

        $written = collect($applied)->reject(fn ($row) => isset($row['skipped']));

        if ($written->isEmpty()) {
            return back()->with('warning', 'Nothing was written — every balance was already settled, or already matched what they earned.');
        }

        try {
            $this->logActivity(
                'adjusted', 'User', $employee->id,
                "{$employee->full_name}'s leave settled to " . $leaving->format('j M Y')
                . ' — ' . $written->map(fn ($r) => "{$r['policy']} {$r['delta']}")->implode(', '),
                ['leaving_date' => $leaving->toDateString(),
                 'policies' => $written->map(fn ($r) => ['policy' => $r['policy'], 'delta' => $r['delta']])->all()]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Settled to ' . $leaving->format('j M Y') . '. '
            . $written->count() . ' balance(s) adjusted — what is left showing is what they are owed.');
    }

    /** The day to settle to: what was asked for, else what is on their record. */
    private function leavingDate(Request $request, User $employee): ?Carbon
    {
        $asked = $request->query('date');

        if ($asked) {
            try {
                return Carbon::parse($asked)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        $recorded = Employee::where('user_id', $employee->id)->value('termination_date');

        try {
            return $recorded ? Carbon::parse($recorded)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function authorizeSettle(): void
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Only HR administrators can settle leave.');
    }
}
