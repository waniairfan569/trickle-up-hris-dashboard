<?php

namespace App\Http\Controllers;

use App\Exports\LeaveEncashmentExport;
use App\Models\LeaveEncashmentRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LeaveEncashmentController extends Controller
{
    /** Admin: all encashment records with year tabs, filters + summary cards. */
    public function index(Request $request)
    {
        $years = LeaveEncashmentRecord::select('renewal_year')->distinct()
            ->orderByDesc('renewal_year')->pluck('renewal_year');
        $year = $this->resolveYear($request, $years);
        $period = $this->normalizePeriod($request->get('period'));

        $records = $this->filteredQuery($request, $year, $period)->latest()->get();

        $all = LeaveEncashmentRecord::where('renewal_year', $year)->get();
        $summary = [
            'pending_amount' => $all->where('status', 'pending')->sum('encashment_amount'),
            'pending_count' => $all->where('status', 'pending')->count(),
            'approved_amount' => $all->where('status', 'approved')->sum('encashment_amount'),
            'paid_amount' => $all->where('status', 'paid')->sum('encashment_amount'),
        ];

        $policies = \App\Models\TimeOffPolicy::orderBy('name')->get(['id', 'name']);

        return view('leave-encashment.index', compact('records', 'years', 'year', 'summary', 'policies', 'period'));
    }

    /** Download the records (respecting year/period/status/policy filters) as Excel. */
    public function export(Request $request)
    {
        $year = $this->resolveYear($request);
        $period = $this->normalizePeriod($request->get('period'));
        $records = $this->filteredQuery($request, $year, $period)->latest()->get();

        return Excel::download(new LeaveEncashmentExport($records), $this->fileStem($year, $period) . '.xlsx');
    }

    /** Download (or ?preview=1 to open inline) the records as a PDF report. */
    public function exportPdf(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $year = $this->resolveYear($request);
        $period = $this->normalizePeriod($request->get('period'));
        $records = $this->filteredQuery($request, $year, $period)->latest()->get();

        $data = [
            'records' => $records,
            'year' => $year,
            'periodLabel' => $this->periodLabel($period),
            'status' => $request->get('status', 'all'),
            'policyName' => $request->filled('policy_id') && $request->policy_id !== 'all'
                ? optional(\App\Models\TimeOffPolicy::find($request->policy_id))->name : null,
            'totals' => [
                'count' => $records->count(),
                'amount' => $records->sum('encashment_amount'),
                'days_encashed' => $records->sum('days_to_encash'),
                'days_lapsed' => $records->sum('days_lapsed'),
                'currency' => optional($records->first())->currency ?: 'PKR',
            ],
            'generatedAt' => now(),
        ];

        $pdf = Pdf::loadView('leave-encashment.pdf', $data)->setPaper('a4', 'landscape');
        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $this->fileStem($year, $period) . '.pdf"',
        ]);
    }

    // --- Filter helpers (shared by index + both exports) ---------------------

    private function resolveYear(Request $request, $years = null): int
    {
        $fallback = ($years && $years->first()) ?: (LeaveEncashmentRecord::max('renewal_year') ?: now()->year);

        return (int) ($request->get('year') ?: $fallback);
    }

    /** Valid period keys: all | h1 | h2 | 01..12 (month of processed_at). */
    private function normalizePeriod(?string $period): string
    {
        if (in_array($period, ['h1', 'h2'], true)) {
            return $period;
        }
        if (is_numeric($period) && (int) $period >= 1 && (int) $period <= 12) {
            return str_pad((string) (int) $period, 2, '0', STR_PAD_LEFT);
        }

        return 'all';
    }

    private function periodLabel(string $period): string
    {
        if ($period === 'h1') {
            return 'First half (Jan–Jun)';
        }
        if ($period === 'h2') {
            return 'Second half (Jul–Dec)';
        }
        if (ctype_digit($period)) {
            return \Carbon\Carbon::createFromFormat('m', $period)->format('F');
        }

        return 'Full year';
    }

    private function filteredQuery(Request $request, int $year, string $period)
    {
        $query = LeaveEncashmentRecord::with(['employee.department', 'policy', 'processedBy'])
            ->where('renewal_year', $year);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('policy_id') && $request->policy_id !== 'all') {
            $query->where('policy_id', $request->policy_id);
        }

        // Half-year / month slices are by when the record was processed.
        if ($period === 'h1') {
            $query->whereNotNull('processed_at')->whereMonth('processed_at', '<=', 6);
        } elseif ($period === 'h2') {
            $query->whereNotNull('processed_at')->whereMonth('processed_at', '>=', 7);
        } elseif (ctype_digit($period)) {
            $query->whereNotNull('processed_at')->whereMonth('processed_at', (int) $period);
        }

        return $query;
    }

    private function fileStem(int $year, string $period): string
    {
        $suffix = $period === 'all' ? '' : '-' . Str::slug($this->periodLabel($period));

        return 'leave-encashments-' . $year . $suffix;
    }

    public function approve(Request $request, LeaveEncashmentRecord $record)
    {
        abort_unless($record->status === 'pending', 422, 'Only pending records can be approved.');

        $record->update([
            'status' => 'approved',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Encashment approved for ' . optional($record->employee)->full_name . '.');
    }

    /** Bulk: mark selected approved/pending records as paid. */
    public function markPaid(Request $request)
    {
        $data = $request->validate([
            'record_ids' => 'required|array|min:1',
            'record_ids.*' => 'integer|exists:leave_encashment_records,id',
            'payment_date' => 'required|date',
            'payment_reference' => 'nullable|string|max:120',
        ]);

        $count = LeaveEncashmentRecord::whereIn('id', $data['record_ids'])
            ->whereIn('status', ['pending', 'approved'])
            ->update([
                'status' => 'paid',
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'payment_date' => $data['payment_date'],
                'payment_reference' => $data['payment_reference'] ?? null,
            ]);

        return back()->with('success', "{$count} encashment(s) marked as paid.");
    }

    public function reject(Request $request, LeaveEncashmentRecord $record)
    {
        $data = $request->validate(['admin_notes' => 'required|string|max:1000']);
        abort_unless(in_array($record->status, ['pending', 'approved'], true), 422);

        $record->update([
            'status' => 'rejected',
            'admin_notes' => $data['admin_notes'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Encashment rejected.');
    }

    /** Employee: my own encashment history. */
    public function myEncashments(Request $request)
    {
        $records = LeaveEncashmentRecord::with(['policy'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('renewal_year')->latest()
            ->get()
            ->groupBy('renewal_year');

        return view('leave-encashment.my', compact('records'));
    }
}
