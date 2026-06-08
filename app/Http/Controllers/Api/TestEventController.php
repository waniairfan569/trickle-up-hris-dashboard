<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Events\{NewCandidateApplied, CandidateStageUpdated, JobStatusChanged, TimeOffRequestCreated, ActivityLogCreated};
use App\Models\{Candidate, Job, TimeOffRequest, ActivityLog};

class TestEventController extends Controller
{
    public function fireEvent(Request $request)
    {
        $event = $request->input('event');
        $companyId = $request->user()->company_id;

        if ($event === 'candidate_applied') {
            $candidate = Candidate::whereHas('job', fn($q) => $q->where('company_id', $companyId))->first();
            if($candidate) event(new NewCandidateApplied($candidate, $companyId));
        } elseif ($event === 'stage_updated') {
            $candidate = Candidate::whereHas('job', fn($q) => $q->where('company_id', $companyId))->first();
            if($candidate) event(new CandidateStageUpdated($candidate));
        } elseif ($event === 'job_status_changed') {
            $job = Job::where('company_id', $companyId)->first();
            if($job) event(new JobStatusChanged($job));
        } elseif ($event === 'timeoff_created') {
            $to = TimeOffRequest::whereHas('employee', fn($q) => $q->where('company_id', $companyId))->first();
            if($to) event(new TimeOffRequestCreated($to, $companyId));
        } elseif ($event === 'activity_created') {
            $log = ActivityLog::where('company_id', $companyId)->first();
            if($log) event(new ActivityLogCreated($log, $companyId));
        }

        return response()->json([
            "fired" => true, 
            "event" => $event, 
            "fired_at" => now()->toIso8601String()
        ]);
    }
}
