<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Sent to HR / super admins when an employee submits feedback or an issue. */
class FeedbackSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Feedback $feedback) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $name = optional($this->feedback->user)->full_name ?? 'An employee';

        return [
            'type' => 'feedback_submitted',
            'urgent' => false,
            'feedback_id' => $this->feedback->id,
            'title' => "New {$this->feedback->categoryLabel()} from {$name}",
            'message' => Str::limit($this->feedback->subject ?: $this->feedback->message, 120),
            'url' => route('feedback.admin'),
            'icon' => 'message-square-warning',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $f = $this->feedback;
        $name = optional($f->user)->full_name ?? 'An employee';

        return (new MailMessage)
            ->subject("📣 New feedback from {$name} — {$f->categoryLabel()}")
            ->greeting('New feedback submitted')
            ->line("**From:** {$name}")
            ->line("**Category:** {$f->categoryLabel()}")
            ->when($f->subject, fn ($m) => $m->line("**Subject:** {$f->subject}"))
            ->line($f->message)
            ->action('Review & respond', route('feedback.admin'));
    }
}
