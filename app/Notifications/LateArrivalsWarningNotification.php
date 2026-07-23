<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once per month when an employee reaches 3 late arrivals: a warning that
 * the NEXT late will deduct half a day from their unplanned leave.
 */
class LateArrivalsWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $lateCount,
        public Carbon $month,
        public ?string $policyName = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $monthLabel = $this->month->format('F Y');
        $cutoff = AttendanceRecord::lateCutoffLabel();
        $policy = $this->policyName ?: 'Unplanned Leave';

        return (new MailMessage)
            ->subject("⚠ Late arrival warning — {$this->lateCount} lates in {$monthLabel}")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("You have now been marked **late {$this->lateCount} times** in {$monthLabel}.")
            ->line('As a reminder of the attendance policy:')
            ->line("• Up to 3 late arrivals in a month carry no penalty — you've used them all.")
            ->line("• **One more late arrival this month will deduct half a day (0.5) from your {$policy} balance.**")
            ->line('• Reaching 6 late arrivals deducts a full day (1.0) in total.')
            ->line("Please make sure you clock in before **{$cutoff}** for the rest of the month.")
            ->action('View my attendance', route('attendance.my-history'))
            ->line('If any of these lates look wrong (e.g. you were on approved leave), please submit a correction or contact HR.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => "⚠ {$this->lateCount} late arrivals this month",
            'message' => 'One more late arrival will deduct half a day from your ' . ($this->policyName ?: 'Unplanned Leave') . ' balance.',
            'url' => route('attendance.my-history'),
        ];
    }
}
