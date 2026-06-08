<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use App\Models\TimeOffRequest;

class TimeOffRequestSubmitted extends Notification
{
    use Queueable;

    public $timeOffRequest;

    public function __construct(TimeOffRequest $timeOffRequest)
    {
        $this->timeOffRequest = $timeOffRequest;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $employeeName = $this->timeOffRequest->employee->full_name ?? 'An employee';
        $policyName = $this->timeOffRequest->policy->name ?? 'leave';
        
        return [
            'type' => 'time_off_submitted',
            'title' => 'New Time Off Request',
            'message' => "{$employeeName} has requested {$this->timeOffRequest->days_requested} day(s) of {$policyName}.",
            'request_id' => $this->timeOffRequest->id,
            'url' => route('time-off.index'),
            'icon' => 'calendar'
        ];
    }
}
