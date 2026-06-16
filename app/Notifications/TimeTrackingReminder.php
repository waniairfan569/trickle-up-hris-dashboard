<?php

namespace App\Notifications;

use App\Models\TimeTrackingPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TimeTrackingReminder extends Notification
{
    use Queueable;

    public function __construct(
        public TimeTrackingPolicy $policy,
        public string $body,
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'time_tracking_reminder',
            'title' => 'Time tracking reminder',
            'message' => $this->body,
            'policy_id' => $this->policy->id,
            'url' => route('attendance.my-history'),
            'icon' => 'timer',
        ];
    }
}
