<?php

namespace App\Notifications;

use App\Models\CompensationClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to HR / super admins when an employee claims compensation leave for overtime. */
class CompensationClaimSubmitted extends Notification
{
    use Queueable;

    public function __construct(public CompensationClaim $claim) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $c = $this->claim;
        $name = optional($c->employee)->full_name ?? 'An employee';

        return [
            'type' => 'compensation_claim_submitted',
            'urgent' => false,
            'compensation_claim_id' => $c->id,
            'title' => "Compensation leave claim from {$name}",
            'message' => "{$c->days_claimed} day(s) for overtime on " . optional($c->worked_date)->format('d M Y') . ' — needs approval.',
            'url' => route('time-off.index', ['tab' => 'team_requests']),
            'icon' => 'timer',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $c = $this->claim;
        $name = optional($c->employee)->full_name ?? 'An employee';

        return (new MailMessage)
            ->subject("⏱ {$name} is claiming compensation leave")
            ->greeting('Compensation leave claim')
            ->line("**{$name}** has claimed time off in lieu of overtime.")
            ->line('**Overtime worked on:** ' . optional($c->worked_date)->format('D, d M Y'))
            ->when($c->hours_worked, fn ($m) => $m->line("**Extra hours:** {$c->hours_worked}"))
            ->line("**Days claimed:** {$c->days_claimed}")
            ->line("**Reason:** {$c->reason}")
            ->action('Review claim', route('time-off.index', ['tab' => 'team_requests']));
    }
}
