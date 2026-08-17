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

        // ── All employees ──────────────────────────────────────────
        $employees = $this->activeEmployees(['department', 'manager', 'workSchedule']);

        if ($request->output === 'preview') {
            $allData = $employees
                ->map(fn ($emp) => $this->reports->getEmployeeReportData($emp, $startDate, $endDate, $request->report_type))
                ->values()->all();

            return view('reports.pdf-template-all', compact('allData', 'periodLabel'));
        }

        // Single employee in the set → one PDF; otherwise a ZIP of per-employee PDFs.
        if ($employees->count() === 1) {
            $data = $this->reports->getEmployeeReportData($employees->first(), $startDate, $endDate, $request->report_type);
            $pdf = Pdf::loadView('reports.pdf-template', compact('data'))->setPaper('a4', 'portrait');

            return $pdf->download($this->getFilename($data));
        }

        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $zipPath = $dir . '/reports_' . now()->format('Ymd_His') . '.zip';

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($employees as $employee) {
            $data = $this->reports->getEmployeeReportData($employee, $startDate, $endDate, $request->report_type);
            $pdf = Pdf::loadView('reports.pdf-template', compact('data'))->setPaper('a4', 'portrait');
            $zip->addFromString($this->getFilename($data), $pdf->output());
        }
        $zip->close();

        $zipName = 'All_Employees_' . $this->slug($periodLabel) . '.zip';

        return response()->download($zipPath, $zipName)->deleteFileAfterSend();
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
