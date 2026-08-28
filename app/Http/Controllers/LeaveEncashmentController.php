<?php

namespace App\Http\Controllers;

use App\Exports\LeaveEncashmentExport;
use App\Models\LeaveEncashmentRecord;
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
        $year = (int) ($request->get('year') ?: ($years->first() ?: now()->year));

        $query = LeaveEncashmentRecord::with(['employee.department', 'policy', 'processedBy'])
            ->where('renewal_year', $year)
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('policy_id') && $request->policy_id !== 'all') {
            $query->where('policy_id', $request->policy_id);
        }

        $records = $query->get();

        $all = LeaveEncashmentRecord::where('renewal_year', $year)->get();
        $summary = [
            'pending_amount' => $all->where('status', 'pending')->sum('encashment_amount'),
            'pending_count' => $all->where('status', 'pending')->count(),
            'approved_amount' => $all->where('status', 'approved')->sum('encashment_amount'),
            'paid_amount' => $all->where('status', 'paid')->sum('encashment_amount'),
        ];

        $policies = \App\Models\TimeOffPolicy::orderBy('name')->get(['id', 'name']);

        return view('leave-encashment.index', compact('records', 'years', 'year', 'summary', 'policies'));
    }

    /** Download the year's encashment records (respecting status/policy filters) as Excel. */
    public function export(Request $request)
    {
        $year = (int) ($request->get('year') ?: (LeaveEncashmentRecord::max('renewal_year') ?: now()->year));

        $records = LeaveEncashmentRecord::with(['employee.department', 'policy', 'processedBy'])
            ->where('renewal_year', $year)
            ->when($request->filled('status') && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('policy_id') && $request->policy_id !== 'all', fn ($q) => $q->where('policy_id', $request->policy_id))
            ->latest()
            ->get();

        $filename = 'leave-encashments-' . $year . '.xlsx';

        return Excel::download(new LeaveEncashmentExport($records), $filename);
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
