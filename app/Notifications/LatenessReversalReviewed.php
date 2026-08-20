<?php

namespace App\Notifications;

use App\Models\LatenessDeduction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class LatenessReversalReviewed extends Notification
{
    use Queueable;

    public function __construct(public LatenessDeduction $deduction, public string $outcome) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $period = Carbon::create($this->deduction->year, $this->deduction->month, 1)->format('F Y');
        $approved = $this->outcome === 'approved';

        return [
            'type'    => 'lateness_reversal_reviewed',
            'title'   => $approved ? 'Late penalty reversed' : 'Late-penalty reversal declined',
            'message' => $approved
                ? "Your {$period} late-arrival penalty was reversed and the days restored."
                : "Your reversal request for the {$period} late-arrival penalty was declined."
                    . ($this->deduction->reversal_response ? " Note: {$this->deduction->reversal_response}" : ''),
            'url'     => route('employees.profile', $this->deduction->user_id),
            'icon'    => $approved ? 'check-circle' : 'x-circle',
        ];
    }
}
