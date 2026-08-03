<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily nudge to HR/admins: these probations have passed their end date but
 * still haven't been confirmed or failed. Sent as one digest per admin.
 *
 * @var array<int, array{name:string, department:?string, days:int, user_id:int}> $items
 */
class ProbationReviewsOverdue extends Notification
{
    use Queueable;

    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $count = count($this->items);
        $plural = $count === 1 ? '' : 's';

        $mail = (new MailMessage)
            ->subject("⏰ {$count} probation review{$plural} overdue")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("The following probation review{$plural} " . ($count === 1 ? 'is' : 'are') . ' overdue and awaiting your decision (confirm or not confirmed):');

        foreach ($this->items as $it) {
            $dept = !empty($it['department']) ? " · {$it['department']}" : '';
            $days = (int) ($it['days'] ?? 0);
            $mail->line("• **{$it['name']}**{$dept} — {$days} day" . ($days === 1 ? '' : 's') . ' overdue');
        }

        return $mail
            ->action('Review probations', route('probation.index'))
            ->line('Please confirm or close out each review as soon as you can.');
    }

    public function toDatabase($notifiable)
    {
        $count = count($this->items);

        return [
            'type' => 'probation_review_overdue',
            'title' => 'Probation reviews overdue',
            'message' => "{$count} probation review" . ($count === 1 ? ' is' : 's are') . ' overdue — please review.',
            'url' => route('probation.index'),
            'icon' => 'alert-triangle',
        ];
    }
}
