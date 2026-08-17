<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\ReportDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ReportGeneratorController extends Controller
{
    public function __construct(private ReportDataService $reports) {}

    /** The report-generator form. */
    public function index()
    {
        $employees = $this->activeEmployees()
            ->map(fn ($u) => [
                'id'         => $u->id,
                'name'       => $u->full_name,
                'initials'   => $u->initials,
                'job_title'  => $u->job_title ?: '—',
                'department' => optional($u->department)->name ?? '—',
            ])->values();

        return view('reports.generator', compact('employees'));
    }

    /** Generate a report — preview (HTML) or download (PDF / ZIP). */
    public function generate(Request $request)
    {
        $request->validate([
            'report_scope' => 'required|in:single,all',
            'report_type'  => 'required|in:monthly,yearly,mid_year,custom',
            'employee_id'  => 'required_if:report_scope,single|nullable|exists:users,id',
            'month'        => 'required_if:report_type,monthly|nullable|integer|min:1|max:12',
            'year'         => 'required|integer|min:2000|max:2100',
            'half'         => 'required_if:report_type,mid_year|nullable|in:first,second',
            'date_from'    => 'required_if:report_type,custom|nullable|date',
            'date_to'      => 'required_if:report_type,custom|nullable|date|after_or_equal:date_from',
            'output'       => 'required|in:pdf,preview',
        ]);

        [$startDate, $endDate, $periodLabel] = $this->getDateRange($request);

        // ── Single employee ────────────────────────────────────────
        if ($request->report_scope === 'single') {
            $employee = User::with(['department', 'manager', 'workSchedule'])->findOrFail($request->employee_id);
            $data = $this->reports->getEmployeeReportData($employee, $startDate, $endDate, $request->report_type);

            if ($request->output === 'preview') {
                return view('reports.pdf-template', compact('data'));
            }

            $pdf = Pdf::loadView('reports.pdf-template', compact('data'))->setPaper('a4', 'portrait');

            return $pdf->download($this->getFilename($data));
        }

        // ── All employees → one consolidated summary table ─────────
        $employees = $this->activeEmployees(['department', 'workSchedule']);
        $summary = $this->reports->getSummaryData($employees, $startDate, $endDate);

        $data = [
            'period_label' => $periodLabel,
            'rows'         => $summary['rows'],
            'totals'       => $summary['totals'],
            'count'        => count($summary['rows']),
            'generated_at' => now()->format('d M Y h:i A'),
            'generated_by' => auth()->user() ? auth()->user()->full_name : 'System',
        ];

        if ($request->output === 'preview') {
            return view('reports.pdf-summary', compact('data'));
        }

        $pdf = Pdf::loadView('reports.pdf-summary', compact('data'))->setPaper('a4', 'landscape');

        return $pdf->download('All_Employees_' . $this->slug($periodLabel) . '.pdf');
    }

    /** Active, non-system employees (real people). */
    private function activeEmployees(array $with = [])
    {
        $realIds = Employee::real()->pluck('user_id')->filter()->all();

        return User::active()
            ->whereIn('id', $realIds)
            ->when($with, fn ($q) => $q->with($with))
            ->orderBy('first_name')->orderBy('last_name')
            ->get();
    }

    /** Resolve [start, end, label] from the report type. */
    private function getDateRange(Request $r): array
    {
        return match ($r->report_type) {
            'monthly' => [
                Carbon::create($r->year, $r->month, 1)->startOfMonth(),
                Carbon::create($r->year, $r->month, 1)->endOfMonth(),
                Carbon::create($r->year, $r->month, 1)->format('F Y'),
            ],
            'yearly' => [
                Carbon::create($r->year, 1, 1)->startOfYear(),
                Carbon::create($r->year, 12, 31)->endOfYear(),
                'Full Year ' . $r->year,
            ],
            'mid_year' => $r->half === 'second'
                ? [Carbon::create($r->year, 7, 1)->startOfDay(), Carbon::create($r->year, 12, 31)->endOfDay(), 'Jul–Dec ' . $r->year]
                : [Carbon::create($r->year, 1, 1)->startOfDay(), Carbon::create($r->year, 6, 30)->endOfDay(), 'Jan–Jun ' . $r->year],
            default => [ // custom
                Carbon::parse($r->date_from)->startOfDay(),
                Carbon::parse($r->date_to)->endOfDay(),
                Carbon::parse($r->date_from)->format('d M') . ' – ' . Carbon::parse($r->date_to)->format('d M Y'),
            ],
        };
    }

    private function getFilename(array $data): string
    {
        return $this->slug($data['employee']['name'] . '_' . $data['meta']['period_label'] . '_Report') . '.pdf';
    }

    /** Filesystem-safe token (keeps ASCII letters/digits, spaces→_). */
    private function slug(string $value): string
    {
        $value = str_replace(['–', '—', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], ['-', '-', '-', '-', '-', '', '', '', '', '', '-'], $value);

        return preg_replace('/\s+/', '_', trim($value));
    }
}
