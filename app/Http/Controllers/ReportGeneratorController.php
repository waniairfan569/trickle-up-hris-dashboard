<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ReportGeneration;
use App\Models\User;
use App\Services\ReportDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $recentCount = ReportGeneration::count();

        return view('reports.generator', compact('employees', 'recentCount'));
    }

    /** Generate a report — preview (HTML) or download (PDF) — and log it to history. */
    public function generate(Request $request)
    {
        $validated = $request->validate([
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

        // A full-company, day-wise PDF builds a large DomPDF frame tree in memory
        // and can span dozens of pages. Give the request room so a big month
        // doesn't run out of memory / time mid-render.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        try {
            [$response, $meta] = $this->renderReport($validated);

            $this->logGeneration($validated, $meta);

            return $response;
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'The report could not be generated. '.$e->getMessage());
        }
    }

    // ── History ───────────────────────────────────────────────────────────────

    /** List every report that has been generated, with filters + counts. */
    public function history(Request $request)
    {
        $scope  = $request->get('scope');
        $type   = $request->get('type');
        $output = $request->get('output');   // downloaded | preview
        $q      = trim((string) $request->get('q'));

        $rows = ReportGeneration::with('generatedBy')
            ->when(in_array($scope, ['single', 'all'], true), fn ($b) => $b->where('report_scope', $scope))
            ->when(in_array($type, ['monthly', 'yearly', 'mid_year', 'custom'], true), fn ($b) => $b->where('report_type', $type))
            ->when($output === 'downloaded', fn ($b) => $b->where('downloads_count', '>', 0))
            ->when($output === 'preview', fn ($b) => $b->where('downloads_count', 0))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w
                ->where('period_label', 'like', "%{$q}%")
                ->orWhere('employee_name', 'like', "%{$q}%")
                ->orWhere('note', 'like', "%{$q}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total'      => ReportGeneration::count(),
            'downloaded' => ReportGeneration::where('downloads_count', '>', 0)->count(),
            'previewed'  => ReportGeneration::where('downloads_count', 0)->count(),
        ];

        return view('reports.history', [
            'rows'   => $rows,
            'stats'  => $stats,
            'scope'  => $scope,
            'type'   => $type,
            'output' => $output,
            'q'      => $q,
        ]);
    }

    /** Re-open a past report as a live preview (regenerated from stored params). */
    public function historyShow(ReportGeneration $generation)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        try {
            [$response] = $this->renderReport($this->paramsFrom($generation, 'preview'));

            return $response;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('reports.history')
                ->with('error', 'That report could not be re-opened. '.$e->getMessage());
        }
    }

    /** Re-download a past report as a PDF (regenerated from stored params). */
    public function historyDownload(ReportGeneration $generation)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        try {
            [$response] = $this->renderReport($this->paramsFrom($generation, 'pdf'));

            $generation->increment('downloads_count');
            $generation->forceFill(['last_downloaded_at' => now()])->save();

            return $response;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('reports.history')
                ->with('error', 'That report could not be downloaded. '.$e->getMessage());
        }
    }

    /** Annotate a history entry with a note. */
    public function historyUpdate(Request $request, ReportGeneration $generation)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:255',
        ]);

        // Normalise an emptied note to NULL rather than storing an empty string.
        $generation->update(['note' => ($validated['note'] ?? null) ?: null]);

        return redirect()->route('reports.history')->with('success', 'Note saved.');
    }

    /** Delete a single history entry. */
    public function historyDestroy(ReportGeneration $generation)
    {
        $generation->delete();

        return redirect()->route('reports.history')->with('success', 'Report removed from history.');
    }

    /** Clear the entire history for this workspace. */
    public function historyClear()
    {
        $n = ReportGeneration::count();
        ReportGeneration::query()->delete();

        return redirect()->route('reports.history')->with('success', "Cleared {$n} report".($n === 1 ? '' : 's').' from history.');
    }

    // ── Internals ───────────────────────────────────────────────────────────────

    /**
     * Build the report response for a set of params.
     * Returns [Response $response, array $meta] where meta carries the labels we
     * snapshot into history (period_label, employee_name).
     */
    private function renderReport(array $p): array
    {
        [$startDate, $endDate, $periodLabel] = $this->getDateRange($p);

        // ── Single employee ────────────────────────────────────────
        if (($p['report_scope'] ?? null) === 'single') {
            $employee = User::with(['department', 'manager', 'workSchedule'])->findOrFail($p['employee_id']);
            $data = $this->reports->getEmployeeReportData($employee, $startDate, $endDate, $p['report_type']);

            $meta = ['period_label' => $periodLabel, 'employee_name' => $employee->full_name];

            if (($p['output'] ?? 'pdf') === 'preview') {
                return [view('reports.pdf-template', compact('data')), $meta];
            }

            $pdf = Pdf::loadView('reports.pdf-template', compact('data'))->setPaper('a4', 'portrait');

            return [$this->downloadPdf($pdf, $this->getFilename($data)), $meta];
        }

        // ── All employees → one consolidated summary table ─────────
        $employees = $this->activeEmployees(['department', 'workSchedule']);
        $withDaily = $startDate->diffInDays($endDate) <= 45;
        $summary = $this->reports->getSummaryData($employees, $startDate, $endDate, $withDaily);

        $data = [
            'period_label' => $periodLabel,
            'rows'         => $summary['rows'],
            'totals'       => $summary['totals'],
            'count'        => count($summary['rows']),
            'generated_at' => now()->format('d M Y h:i A'),
            'generated_by' => auth()->user() ? auth()->user()->full_name : 'System',
        ];

        $meta = ['period_label' => $periodLabel, 'employee_name' => null];

        if (($p['output'] ?? 'pdf') === 'preview') {
            return [view('reports.pdf-summary', compact('data')), $meta];
        }

        $pdf = Pdf::loadView('reports.pdf-summary', compact('data'))->setPaper('a4', 'landscape');

        return [$this->downloadPdf($pdf, 'All_Employees_' . $this->slug($periodLabel) . '.pdf'), $meta];
    }

    /** Record a generation in history. */
    private function logGeneration(array $p, array $meta): void
    {
        $type = $p['report_type'];
        $downloaded = ($p['output'] ?? 'pdf') === 'pdf';

        ReportGeneration::create([
            'generated_by'       => auth()->id(),
            'report_scope'       => $p['report_scope'],
            'report_type'        => $type,
            'employee_id'        => $p['report_scope'] === 'single' ? ($p['employee_id'] ?? null) : null,
            'employee_name'      => $meta['employee_name'] ?? null,
            'month'              => $type === 'monthly' ? ($p['month'] ?? null) : null,
            'year'               => in_array($type, ['monthly', 'yearly', 'mid_year'], true) ? ($p['year'] ?? null) : null,
            'half'               => $type === 'mid_year' ? ($p['half'] ?? null) : null,
            'date_from'          => $type === 'custom' ? ($p['date_from'] ?? null) : null,
            'date_to'            => $type === 'custom' ? ($p['date_to'] ?? null) : null,
            'period_label'       => $meta['period_label'],
            'output'             => $p['output'] ?? 'pdf',
            'downloads_count'    => $downloaded ? 1 : 0,
            'last_downloaded_at' => $downloaded ? now() : null,
        ]);
    }

    /** Reconstruct render params from a stored history entry. */
    private function paramsFrom(ReportGeneration $g, string $output): array
    {
        return [
            'report_scope' => $g->report_scope,
            'report_type'  => $g->report_type,
            'employee_id'  => $g->employee_id,
            'month'        => $g->month,
            'year'         => $g->year,
            'half'         => $g->half,
            'date_from'    => optional($g->date_from)->toDateString(),
            'date_to'      => optional($g->date_to)->toDateString(),
            'output'       => $output,
        ];
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

    /** Resolve [start, end, label] from the report params (array form). */
    private function getDateRange(array $p): array
    {
        $year = (int) ($p['year'] ?? now()->year);

        return match ($p['report_type']) {
            'monthly' => [
                Carbon::create($year, (int) $p['month'], 1)->startOfMonth(),
                Carbon::create($year, (int) $p['month'], 1)->endOfMonth(),
                Carbon::create($year, (int) $p['month'], 1)->format('F Y'),
            ],
            'yearly' => [
                Carbon::create($year, 1, 1)->startOfYear(),
                Carbon::create($year, 12, 31)->endOfYear(),
                'Full Year ' . $year,
            ],
            'mid_year' => ($p['half'] ?? 'first') === 'second'
                ? [Carbon::create($year, 7, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay(), 'Jul–Dec ' . $year]
                : [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 6, 30)->endOfDay(), 'Jan–Jun ' . $year],
            default => [ // custom
                Carbon::parse($p['date_from'])->startOfDay(),
                Carbon::parse($p['date_to'])->endOfDay(),
                Carbon::parse($p['date_from'])->format('d M') . ' – ' . Carbon::parse($p['date_to'])->format('d M Y'),
            ],
        };
    }

    /**
     * Emit a rendered PDF as a download with a guaranteed-clean binary body.
     * Render to a string first, then discard leftover output buffers so a stray
     * notice / whitespace can never be prepended to the "%PDF" bytes.
     */
    private function downloadPdf($pdf, string $filename)
    {
        $content = $pdf->output();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
            'Content-Length'      => (string) strlen($content),
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
            'Pragma'              => 'public',
        ]);
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
