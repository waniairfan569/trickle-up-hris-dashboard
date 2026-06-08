<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use App\Models\Company;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    /** GET /v1/work-schedule — return all 7 days for this company */
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $schedule = WorkSchedule::where('company_id', $companyId)
            ->orderBy('day_of_week')
            ->get()
            ->map(fn($s) => array_merge($s->toArray(), [
                'day_label'        => WorkSchedule::DAY_LABELS[$s->day_of_week] ?? '',
                'expected_minutes' => $s->expected_minutes,
            ]));

        return response()->json($schedule);
    }

    /** PUT /v1/work-schedule — bulk upsert all 7 days at once */
    public function update(Request $request)
    {
        $companyId = $request->user()->company_id;

        $request->validate([
            'schedule'                     => 'required|array|min:7|max:7',
            'schedule.*.day_of_week'       => 'required|integer|between:1,7',
            'schedule.*.start_time'        => 'required|date_format:H:i',
            'schedule.*.end_time'          => 'required|date_format:H:i|after:schedule.*.start_time',
            'schedule.*.break_minutes'     => 'required|integer|min:0|max:480',
            'schedule.*.is_working_day'    => 'required|boolean',
        ]);

        foreach ($request->schedule as $day) {
            WorkSchedule::updateOrCreate(
                ['company_id' => $companyId, 'day_of_week' => $day['day_of_week']],
                [
                    'start_time'     => $day['start_time'] . ':00',
                    'end_time'       => $day['end_time'] . ':00',
                    'break_minutes'  => $day['break_minutes'],
                    'is_working_day' => $day['is_working_day'],
                ]
            );
        }

        return $this->index($request);
    }

    /** GET /v1/company-settings — return company-level settings */
    public function getCompanySettings(Request $request)
    {
        $company = Company::findOrFail($request->user()->company_id);
        return response()->json([
            'leave_unit' => $company->leave_unit ?? 'days',
        ]);
    }

    /** PUT /v1/company-settings — super admin updates company-level settings */
    public function updateCompanySettings(Request $request)
    {
        $request->validate([
            'leave_unit' => 'required|in:days,hours',
        ]);

        $company = Company::findOrFail($request->user()->company_id);
        $company->update(['leave_unit' => $request->leave_unit]);

        return response()->json([
            'leave_unit' => $company->leave_unit,
            'message'    => 'Company settings updated.',
        ]);
    }
}
