<?php

namespace App\Http\Controllers;

use App\Models\AdminReminderSetting;
use App\Models\OvertimeReportRun;
use App\Models\User;
use App\Services\OvertimeReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Finance "approved overtime" report: pick a period (day / month / year / custom),
 * see the approved Overtime Approval Form submissions per employee, export to
 * CSV or PDF, keep a history of exports, and configure a recurring reminder.
 */
class OvertimeReportController extends Controller
{
    public function __construct(private OvertimeReportService $service) {}

    public function index(Request $request)
    {
        [$from, $to, $label, $preset] = $this->resolvePeriod($request);

        $report = $this->service->build($from, $to);

        $settings = AdminReminderSetting::getSettings();

        $adminUsers = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))
            ->where('account_status', 'active')
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $history = OvertimeReportRun::with('generator')->orderByDesc('created_at')->limit(15)->get();

        return view('overtime-report.index', array_merge($report, [
            'from' => $from, 'to' => $to, 'periodLabel' => $label, 'preset' => $preset,
            'settings' => $settings, 'adminUsers' => $adminUsers, 'history' => $history,
            'brandName' => \App\Tenancy\Brand::name(),
        ]));
    }

    public function export(Request $request)
    {
        [$from, $to, $label] = $this->resolvePeriod($request);
        $report = $this->service->build($from, $to);
        $format = $request->input('format') === 'csv' ? 'csv' : 'pdf';

        // Log the export so finance has a trail of what has been pulled.
        try {
            OvertimeReportRun::create([
                'period_from'    => $from->toDateString(),
                'period_to'      => $to->toDateString(),
                'label'          => $label,
                'format'         => $format,
                'entry_count'    => $report['entryCount'],
                'employee_count' => $report['employeeCount'],
                'total_hours'    => $report['totalHours'],
                'generated_by'   => $request->user()->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $slug = Str::slug('overtime ' . $from->format('Y-m-d') . ' to ' . $to->format('Y-m-d')) ?: 'overtime-report';

        if ($format === 'csv') {
            return $this->csv($report, $slug);
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $pdf = Pdf::loadView('reports.overtime-payout', array_merge($report, [
            'from' => $from, 'to' => $to, 'periodLabel' => $label,
            'brandName' => \App\Tenancy\Brand::name(), 'generatedAt' => now(),
        ]))->setPaper('a4', 'portrait');

        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $slug . '.pdf"',
            'Content-Length'      => (string) strlen($content),
        ]);
    }

    private function csv(array $report, string $slug)
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, ['Employee', 'Email', 'Overtime date', 'Hours', 'Details', 'Approved on', 'Approved by']);
            foreach ($report['rows'] as $r) {
                fputcsv($out, [
                    $r->employee,
                    $r->email,
                    optional($r->date)->format('Y-m-d'),
                    $r->hours !== null ? $r->hours : '',
                    $r->details,
                    optional($r->approved_on)->format('Y-m-d H:i'),
                    $r->approved_by,
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['TOTAL', '', '', $report['totalHours'], $report['entryCount'] . ' entries · ' . $report['employeeCount'] . ' employees', '', '']);
            fclose($out);
        }, $slug . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function saveReminder(Request $request)
    {
        $data = $request->validate([
            'overtime_frequency'    => 'required|in:monthly,weekly',
            'overtime_day'          => 'nullable|integer|min:1|max:31',
            'overtime_weekday'      => 'nullable|integer|min:1|max:7',
            'overtime_send_time'    => 'required|date_format:H:i',
            'overtime_recipients'   => 'nullable|array',
            'overtime_recipients.*' => 'integer|exists:users,id',
        ]);

        $s = AdminReminderSetting::getSettings();
        $s->overtime_enabled  = $request->boolean('overtime_enabled');
        $s->overtime_frequency = $data['overtime_frequency'];
        $s->overtime_day      = $data['overtime_day'] ?? 1;
        $s->overtime_weekday  = $data['overtime_weekday'] ?? 1;
        $s->overtime_send_time = $data['overtime_send_time'] . ':00';
        $s->overtime_recipients = $data['overtime_recipients'] ?? [];
        $s->save();

        $msg = $s->overtime_enabled
            ? 'Overtime report reminder on — ' . $s->overtimeScheduleLabel() . '.'
            : 'Overtime report reminder turned off.';

        return back()->with('success', $msg);
    }

    /** [from, to, label, preset] from a preset or custom range. Defaults to this month. */
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
            $lastMonth = $today->copy()->subMonthNoOverflow();
            [$from, $to, $label] = match ($preset) {
                'today'      => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today (' . $today->format('d M Y') . ')'],
                'week'       => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(), 'This week'],
                'last-month' => [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth(), $lastMonth->format('F Y')],
                'quarter'    => [$today->copy()->startOfQuarter(), $today->copy()->endOfQuarter(), 'This quarter'],
                'half'       => [$today->copy()->subMonths(6)->startOfDay(), $today->copy()->endOfDay(), 'Last 6 months'],
                'year'       => [$today->copy()->startOfYear(), $today->copy()->endOfYear(), 'This year (' . $today->format('Y') . ')'],
                default      => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), $today->format('F Y')],
            };
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to, $label, $preset];
    }
}
