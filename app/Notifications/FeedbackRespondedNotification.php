<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Sent to the employee when HR replies to their feedback / issue. */
class FeedbackRespondedNotification extends Notification
{
    use Queueable;

    public function __construct(public Feedback $feedback) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'feedback_responded',
            'urgent' => false,
            'feedback_id' => $this->feedback->id,
            'title' => 'HR replied to your feedback',
            'message' => Str::limit($this->feedback->admin_response, 120),
            'url' => route('dashboard'),
            'icon' => 'message-square-reply',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $f = $this->feedback;

        return (new MailMessage)
            ->subject('💬 HR replied to your feedback')
            ->greeting('You have a reply')
            ->line('HR responded to your feedback' . ($f->subject ? " “{$f->subject}”" : '') . ':')
            ->line($f->admin_response)
            ->line('Status: ' . $f->statusBadge()[0])
            ->action('View on your dashboard', route('dashboard'));
    }
}
