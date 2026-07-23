<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use App\Models\LeaveYearSetting;
use App\Models\TimeOffPolicy;
use App\Services\LeaveRenewalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveYearSettingsController extends Controller
{
    /** Settings list, grouped by company, with due-renewal alerts. */
    public function index()
    {
        $settings = LeaveYearSetting::with(['entity', 'policy'])
            ->orderBy('company_entity_id')->orderBy('policy_id')
            ->get();

        $due = $settings->filter(fn ($s) => $s->is_active && $s->isDueForRenewal());
        $recentLogs = \App\Models\LeaveRenewalLog::with('policy')
            ->latest('started_at')->take(10)->get();

        return view('leave-year-settings.index', compact('settings', 'due', 'recentLogs'));
    }

    public function create()
    {
        return view('leave-year-settings.form', [
            'setting' => new LeaveYearSetting([
                'year_start_month' => 7, 'year_start_day' => 1,
                'encashment_enabled' => true, 'encashment_type' => 'percent_of_annual',
                'encashment_value' => 10, 'working_days_per_month' => 26,
                'pro_rata_enabled' => true, 'pro_rata_cutoff_day' => 15,
                'pro_rata_round_to' => 'half', 'auto_renewal_enabled' => true,
                'is_active' => true,
            ]),
            'policies' => TimeOffPolicy::active()->orderBy('name')->get(),
            'entities' => CompanyEntity::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        LeaveYearSetting::create($data); // next_renewal_date auto-set on saving

        return redirect()->route('leave-year-settings.index')->with('success', 'Leave year settings created.');
    }

    public function edit(LeaveYearSetting $leaveYearSetting)
    {
        return view('leave-year-settings.form', [
            'setting' => $leaveYearSetting,
            'policies' => TimeOffPolicy::active()->orderBy('name')->get(),
            'entities' => CompanyEntity::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, LeaveYearSetting $leaveYearSetting)
    {
        $leaveYearSetting->update($this->validated($request));

        return redirect()->route('leave-year-settings.index')->with('success', 'Leave year settings updated.');
    }

    public function destroy(LeaveYearSetting $leaveYearSetting)
    {
        if ($leaveYearSetting->encashmentRecords()->exists()) {
            return back()->with('error', 'Cannot delete — encashment records exist for this setting. Deactivate it instead.');
        }
        $leaveYearSetting->delete();

        return back()->with('success', 'Leave year setting deleted.');
    }

    /** DRY RUN — table of what the renewal would do. Nothing is written. */
    public function previewRenewal(LeaveYearSetting $leaveYearSetting, LeaveRenewalService $service)
    {
        $rows = $service->previewRenewal($leaveYearSetting);

        $summary = [
            'total' => count($rows),
            'with_encashment' => collect($rows)->where('days_to_encash', '>', 0)->count(),
            'zero_remaining' => collect($rows)->where('days_remaining', '<=', 0)->count(),
            'total_amount' => collect($rows)->sum('encashment_amount'),
            'total_lapsed' => collect($rows)->sum('days_lapsed'),
        ];

        return view('leave-year-settings.preview', [
            'setting' => $leaveYearSetting->load('policy'),
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    /** Run the renewal for real (requires typing RENEW). */
    public function manualRenewal(Request $request, LeaveYearSetting $leaveYearSetting, LeaveRenewalService $service)
    {
        $request->validate([
            'confirm_text' => 'required|in:RENEW',
        ], ['confirm_text.in' => 'Type RENEW (in capitals) to confirm.']);

        $summary = $service->runRenewal($leaveYearSetting, 'manual', $request->user());

        return redirect()->route('leave-year-settings.index')->with('success',
            "Renewal completed — {$summary['total_employees']} employee(s) processed, "
            . "{$summary['with_encashment']} with encashment, PKR "
            . number_format($summary['total_amount'], 2) . ' total, '
            . "{$summary['total_lapsed']} day(s) lapsed.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'company_entity_id' => 'nullable|exists:company_entities,id',
            'policy_id' => 'required|exists:time_off_policies,id',
            'name' => 'required|string|max:120',
            'year_start_month' => 'required|integer|between:1,12',
            'year_start_day' => 'required|integer|between:1,28',
            'encashment_type' => ['required', Rule::in(['percent_of_annual', 'full_remaining', 'fixed_days', 'none'])],
            'encashment_value' => 'required|numeric|min:0|max:1000',
            'working_days_per_month' => 'required|integer|between:1,31',
            'carry_forward_max_days' => 'nullable|numeric|min:0|max:365',
            'pro_rata_cutoff_day' => 'required|integer|between:1,28',
            'pro_rata_round_to' => ['required', Rule::in(['none', 'half', 'full'])],
        ]);

        $data['encashment_enabled'] = $data['encashment_type'] !== 'none';
        $data['carry_forward_enabled'] = $request->boolean('carry_forward_enabled');
        $data['pro_rata_enabled'] = $request->boolean('pro_rata_enabled');
        $data['auto_renewal_enabled'] = $request->boolean('auto_renewal_enabled');
        $data['is_active'] = $request->boolean('is_active', true);
        if (!$data['carry_forward_enabled']) {
            $data['carry_forward_max_days'] = null;
        }

        return $data;
    }
}
