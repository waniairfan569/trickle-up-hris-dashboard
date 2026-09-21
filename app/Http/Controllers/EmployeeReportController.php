<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\ConductNote;
use App\Models\TimeOffRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Per-employee time-tracking report: planned / unplanned / work-from-home leaves
 * (with reasons), lates (with reasons), and the admin conduct log — for a chosen
 * period (presets or a custom range). Viewable in-browser or as a PDF download.
 */
class EmployeeReportController extends Controller
{
    /** Policy-name keywords that mark a leave as "unplanned" (mirrors ReportDataService). */
    private const UNPLANNED = ['unplanned', 'casual', 'sick', 'emergency'];

    public function show(Request $request, User $employee)
    {
        [$from, $to, $label] = $this->resolvePeriod($request);

        // Approved leaves overlapping the period.
        $leaves = TimeOffRequest::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->with('policy')
            ->orderBy('start_date')
            ->get();

        $planned = collect();
        $unplanned = collect();
        $wfh = collect();
        foreach ($leaves as $leave) {
            $policy = $leave->policy;
            if ($policy && $policy->isWorkFromHome()) {
                $wfh->push($leave);
                continue;
            }
            $name = Str::lower(optional($policy)->name ?? '');
            Str::contains($name, self::UNPLANNED) ? $unplanned->push($leave) : $planned->push($leave);
        }

        // Lates in the period.
        $lates = AttendanceRecord::where('user_id', $employee->id)
            ->where('status', 'late')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        // Late explanations (corrections) keyed by date, so each late can show a reason.
        $corrections = AttendanceCorrection::where('user_id', $employee->id)
            ->whereBetween('correction_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($c) => Carbon::parse($c->correction_date)->toDateString());

        // Admin conduct / behaviour log in the period.
        $conduct = ConductNote::where('user_id', $employee->id)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->with('author')
            ->orderBy('occurred_on')
            ->get();

        $data = [
            'employee'    => $employee,
            'from'        => $from,
            'to'          => $to,
            'periodLabel' => $label,
            'planned'     => $planned,
            'unplanned'   => $unplanned,
            'wfh'         => $wfh,
            'lates'       => $lates,
            'corrections' => $corrections,
            'conduct'     => $conduct,
            'generatedAt' => now(),
            'brandName'   => \App\Tenancy\Brand::name(),
        ];

        if ($request->input('format') === 'pdf') {
            @ini_set('memory_limit', '512M');
            @set_time_limit(120);

            $pdf = Pdf::loadView('reports.employee-time-tracking', $data)->setPaper('a4', 'portrait');
            $content = $pdf->output();
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $filename = Str::slug($employee->full_name . ' time report ' . $from->format('Y-m-d') . ' ' . $to->format('Y-m-d'));

            return response($content, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . ($filename ?: 'time-report') . '.pdf"',
                'Content-Length'      => (string) strlen($content),
            ]);
        }

        return view('reports.employee-time-tracking', $data);
    }

    /** [from, to, label] from a preset or a custom from/to range. Defaults to this month. */
    private function resolvePeriod(Request $request): array
    {
        $today = Carbon::today();
        $preset = $request->input('preset', 'month');

        if ($preset === 'custom' && $request->filled('from') && $request->filled('to')) {
            try {
                $from = Carbon::parse($request->input('from'))->startOfDay();
                $to = Carbon::parse($request->input('to'))->endOfDay();
            } catch (\Throwable $e) {
                $from = $today->copy()->startOfMonth();
                $to = $today->copy()->endOfMonth();
            }
            $label = $from->format('d M Y') . ' – ' . $to->format('d M Y');
        } else {
            [$from, $to, $label] = match ($preset) {
                'today'   => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today (' . $today->format('d M Y') . ')'],
                'week'    => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(), 'This week'],
                'quarter' => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter(), 'This quarter'],
                'half'    => [$today->copy()->subMonths(6)->startOfDay(), $today->copy()->endOfDay(), 'Last 6 months'],
                'year'    => [$today->copy()->startOfYear(), $today->copy()->endOfYear(), 'This year (' . $today->format('Y') . ')'],
                default   => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $today->format('F Y')],
            };
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, $label];
    }
}
