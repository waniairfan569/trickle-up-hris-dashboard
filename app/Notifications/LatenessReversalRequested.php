<?php

namespace App\Notifications;

use App\Models\LatenessDeduction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class LatenessReversalRequested extends Notification
{
    use Queueable;

    public function __construct(public LatenessDeduction $deduction) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $period = Carbon::create($this->deduction->year, $this->deduction->month, 1)->format('F Y');
        $name = optional($this->deduction->employee)->full_name ?? 'An employee';

        return [
            'type'    => 'lateness_reversal_requested',
            'title'   => 'Late-penalty reversal requested',
            'message' => "{$name} asked to reverse their {$period} late-arrival penalty.",
            'url'     => route('employees.profile', $this->deduction->user_id),
            'icon'    => 'alarm-clock',
        ];
    }
}
