<?php

namespace App\Notifications;

use App\Models\CompensationClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the employee when HR approves or declines their compensation leave claim. */
class CompensationClaimReviewed extends Notification
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
        $approved = $c->status === 'approved';
        $date = optional($c->worked_date)->format('d M Y');

        return [
            'type' => 'compensation_claim_reviewed',
            'urgent' => false,
            'compensation_claim_id' => $c->id,
            'title' => $approved ? 'Compensation leave credited' : 'Compensation leave claim declined',
            'message' => $approved
                ? "{$c->days_credited} day(s) credited for overtime on {$date}. You can now request it as leave."
                : "Your claim for overtime on {$date} was declined" . ($c->review_note ? ": {$c->review_note}" : '.'),
            'url' => route('time-off.index', ['tab' => 'my_requests']),
            'icon' => $approved ? 'check-circle' : 'x-circle',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $c = $this->claim;
        $approved = $c->status === 'approved';
        $date = optional($c->worked_date)->format('D, d M Y');

        $mail = (new MailMessage)
            ->subject($approved ? '✅ Compensation leave credited' : 'Compensation leave claim declined')
            ->greeting($approved ? 'Your compensation leave has been credited' : 'Your compensation leave claim was declined')
            ->line("Overtime worked on **{$date}** — you claimed {$c->days_claimed} day(s).");

        if ($approved) {
            $mail->line("**{$c->days_credited} day(s)** have been added to your Compensation Leave balance. You can request them as time off whenever you like.");
        } elseif ($c->review_note) {
            $mail->line("**Note from HR:** {$c->review_note}");
        }

        return $mail->action('View time off', route('time-off.index', ['tab' => 'my_requests']));
    }
}
