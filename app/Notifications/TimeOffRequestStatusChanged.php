<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use App\Models\TimeOffRequest;

class TimeOffRequestStatusChanged extends Notification
{
    use Queueable;

    public $timeOffRequest;
    public $status;

    public function __construct(TimeOffRequest $timeOffRequest, $status)
    {
        $this->timeOffRequest = $timeOffRequest;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $policyName = $this->timeOffRequest->policy->name ?? 'leave';
        $icon = $this->status === 'approved' ? 'check-circle' : 'x-circle';
        
        return [
            'type' => 'time_off_status_changed',
            'title' => 'Time Off Request ' . ucfirst($this->status),
            'message' => "Your request for {$this->timeOffRequest->days_requested} day(s) of {$policyName} has been {$this->status}.",
            'request_id' => $this->timeOffRequest->id,
            'url' => route('time-off.index'),
            'icon' => $icon
        ];
    }
}
