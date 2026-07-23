<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when the monthly lateness penalty is actually applied: 4 lates → 0.5 day
 * deducted from unplanned leave, 6 lates → 1.0 day (the second half-day).
 */
class LatenessDeductionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public float $deductedNow,
        public float $totalThisMonth,
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
        $policy = $this->policyName ?: 'Unplanned Leave';
        $now = rtrim(rtrim(number_format($this->deductedNow, 1), '0'), '.');
        $total = rtrim(rtrim(number_format($this->totalThisMonth, 1), '0'), '.');

        $mail = (new MailMessage)
            ->subject("Leave deducted — {$this->lateCount} late arrivals in {$monthLabel}")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("You have been marked **late {$this->lateCount} times** in {$monthLabel}.")
            ->line("Per the attendance policy, **{$now} day has been deducted from your {$policy} balance** (total {$total} day" . ($this->totalThisMonth == 1 ? '' : 's') . " deducted this month).");

        if ($this->totalThisMonth < 1.0) {
            $mail->line('Reaching 6 late arrivals this month will deduct another half day (1.0 in total) — please arrive on time for the rest of the month.');
        }

        return $mail
            ->action('View my attendance', route('attendance.my-history'))
            ->line('If any of these lates look wrong (e.g. you were on approved leave), please submit a correction or contact HR.');
    }

    public function toDatabase($notifiable): array
    {
        $now = rtrim(rtrim(number_format($this->deductedNow, 1), '0'), '.');

        return [
            'title' => "⏰ {$now} day deducted for late arrivals",
            'message' => "{$this->lateCount} lates in {$this->month->format('F')} — deducted from your " . ($this->policyName ?: 'Unplanned Leave') . ' balance.',
            'url' => route('attendance.my-history'),
        ];
    }
}
