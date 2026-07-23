<?php

namespace App\Notifications;

use App\Models\LeaveYearSetting;
use App\Models\TimeOffPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Year-end leave renewal summary for one employee. */
class LeaveRenewalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TimeOffPolicy $policy,
        public string $yearLabel,
        public float $allocation,
        public float $remaining,
        public float $encashmentCap,
        public float $daysToEncash,
        public float $encashmentAmount,
        public float $daysLapsed,
        public float $carryForward,
        public float $newAllocation,
        public bool $isProRata,
        public ?int $proRataMonths,
        public LeaveYearSetting $setting,
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        $msg = $this->daysToEncash > 0
            ? "Encashed {$this->daysToEncash} day(s) — PKR " . number_format($this->encashmentAmount, 2) . ' (pending approval).'
            : ($this->remaining > 0 ? "{$this->daysLapsed} day(s) lapsed." : 'All leaves were used — nothing to encash.');

        return [
            'type' => 'leave_renewed',
            'title' => "🏖 Your {$this->policy->name} has been renewed",
            'message' => "New balance: " . rtrim(rtrim(number_format($this->newAllocation + $this->carryForward, 1), '0'), '.') . " days. {$msg}",
            'url' => route('leave-encashments.my'),
            'icon' => 'calendar-check',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $fmtD = fn ($d) => rtrim(rtrim(number_format((float) $d, 1), '0'), '.');
        $fmtA = fn ($a) => 'PKR ' . number_format((float) $a, 2);

        $mail = (new MailMessage)
            ->subject("Your {$this->policy->name} has been renewed")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line("Your leave year has ended. Here is your summary for **{$this->yearLabel}**:")
            ->line("**Policy:** {$this->policy->name}")
            ->line('**Allocation:** ' . $fmtD($this->allocation) . ' days'
                . ($this->isProRata ? " _(pro-rata, {$this->proRataMonths} months)_" : ''))
            ->line('**Days remaining at year end:** ' . $fmtD($this->remaining) . ' days');

        if ($this->daysToEncash > 0) {
            // Scenario 1/2 — encashment happened (possibly capped).
            $mail->line('---')
                ->line('**Encashment**')
                ->line('Rule: ' . $this->setting->encashmentRuleLabel())
                ->line('Encashment cap: ' . $fmtD($this->encashmentCap) . ' days')
                ->line('**Days encashed: ' . $fmtD($this->daysToEncash) . ' days ✅**')
                ->line('Amount: **' . $fmtA($this->encashmentAmount) . '** _(pending approval)_');

            if ($this->daysLapsed > 0) {
                $mail->line('⚠ **Days lapsed: ' . $fmtD($this->daysLapsed) . ' days** — remaining above the cap does not carry over.');
            }
        } elseif ($this->remaining > 0) {
            // Had days left but no encashment on this policy.
            $mail->line('---')
                ->line('No encashment applies on this policy — **' . $fmtD($this->daysLapsed) . ' day(s) lapsed**.');
        } else {
            // Scenario 3 — nothing left.
            $mail->line('---')
                ->line('Days remaining: 0 — no encashment (nothing left to encash). Great job using all your leaves!');
        }

        $mail->line('---')
            ->line('**New year balance**')
            ->line('Fresh allocation: ' . $fmtD($this->newAllocation) . ' days');
        if ($this->carryForward > 0) {
            $mail->line('Carried forward: ' . $fmtD($this->carryForward) . ' days');
        }

        return $mail
            ->line('**Total available: ' . $fmtD($this->newAllocation + $this->carryForward) . ' days**')
            ->action('View my encashments', route('leave-encashments.my'));
    }
}
