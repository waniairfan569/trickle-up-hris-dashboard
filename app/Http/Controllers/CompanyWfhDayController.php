<?php

namespace App\Http\Controllers;

use App\Exports\CompanyWfhDaysExport;
use App\Models\CompanyWfhDay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CompanyWfhDayController extends Controller
{
    /** Manage the company-wide work-from-home days. */
    public function index()
    {
        $upcoming = CompanyWfhDay::whereDate('date', '>=', today())->orderBy('date')->get();
        $past     = CompanyWfhDay::whereDate('date', '<', today())->orderByDesc('date')->limit(24)->get();

        return view('company-wfh-days.index', compact('upcoming', 'past'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $date = Carbon::parse($data['date'])->toDateString();

        CompanyWfhDay::firstOrCreate(
            ['date' => $date],
            ['note' => $data['note'] ?? null, 'created_by' => auth()->id()]
        );

        return back()->with('success', 'Company WFH day set for ' . Carbon::parse($date)->format('D, d M Y') . ' — all employees clock in via the dashboard that day.');
    }

    public function destroy(CompanyWfhDay $companyWfhDay)
    {
        $companyWfhDay->delete();

        return back()->with('success', 'Company WFH day removed.');
    }

    /** Download the WFH-days sheet as Excel. */
    public function export()
    {
        return Excel::download(new CompanyWfhDaysExport, 'company-wfh-days.xlsx');
    }

    /** Download (or ?preview=1 to open inline) the WFH-days sheet as a PDF. */
    public function exportPdf(Request $request)
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        $days = CompanyWfhDay::with('creator')->orderBy('date')->get();

        $pdf = Pdf::loadView('company-wfh-days.pdf', [
            'days' => $days,
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait');

        $content = $pdf->output();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $disposition = $request->boolean('preview') ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="company-wfh-days.pdf"',
        ]);
    }
}
