<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HrDocumentAutoGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Admin actions to (re)generate lateness / return-to-work draft documents. */
class LatenessDocumentController extends Controller
{
    public function __construct(private HrDocumentAutoGenerator $generator) {}

    /** Catch up any missing per-day lateness + return-to-work drafts for this employee now. */
    public function generate(Request $request, User $employee)
    {
        $lookback = (int) $request->input('lookback', 90);
        $count = $this->generator->generateForEmployee($employee, $lookback);

        return redirect()
            ->route('employees.profile', ['employee' => $employee->id, 'section' => 'timetracking'])
            ->with('success', $count > 0
                ? "Generated {$count} draft document(s) — review and send them below."
                : 'No missing documents — everything is already generated.');
    }

    /** Generate the consolidated monthly Lateness Review for the chosen month. */
    public function monthly(Request $request, User $employee)
    {
        $data = $request->validate(['month' => 'required|date_format:Y-m']);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        $doc = $this->generator->generateMonthlyLateness($employee, $month, $request->user()->id);

        $message = $doc
            ? "Monthly Lateness Review for {$month->format('M Y')} is ready — review and send it below."
            : "No late days for {$employee->first_name} in {$month->format('M Y')}, so nothing was generated.";

        return redirect()
            ->route('employees.profile', ['employee' => $employee->id, 'section' => 'timetracking'])
            ->with('success', $message);
    }
}
