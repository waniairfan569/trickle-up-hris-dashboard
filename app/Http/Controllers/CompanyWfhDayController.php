<?php

namespace App\Http\Controllers;

use App\Models\CompanyWfhDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
}
