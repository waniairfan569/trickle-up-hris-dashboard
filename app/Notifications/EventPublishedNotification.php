<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to employees when an admin publishes a company event to their calendar. */
class EventPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public Event $event) {}

    public function via($notifiable)
    {
        // Email only when the admin asked to notify; the bell (database) always
        // records it. The central NotificationSending gate still lets each
        // employee mute the "events" category.
        return $this->event->notify_employees ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $when = $this->event->date?->format('D, d M Y');

        return [
            'type' => 'info',
            'urgent' => false,
            'event_id' => $this->event->id,
            'title' => '📅 New event: ' . $this->event->title,
            'message' => trim($this->event->title . ($when ? " on {$when}" : '')
                . ($this->event->location ? " · {$this->event->location}" : '')),
            'date' => optional($this->event->date)->toDateString(),
            'location' => $this->event->location,
            'color' => $this->event->color_hex,
            'url' => route('events.employee-calendar'),
            'icon' => 'calendar-days',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $e = $this->event;
        $dates = optional($e->date)->format('D, d M Y');
        if ($e->is_multi_day) {
            $dates .= ' — ' . $e->end_date->format('D, d M Y');
        }

        $mail = (new MailMessage)
            ->subject('📅 New Company Event: ' . $e->title)
            ->greeting('A new event has been scheduled')
            ->line('**' . $e->title . '**');

        if ($dates) {
            $mail->line('📅 ' . $dates);
        }
        if ($e->location) {
            $mail->line('📍 ' . $e->location);
        }
        if ($e->description) {
            $mail->line($e->description);
        }

        return $mail->action('View in calendar', route('events.employee-calendar'));
    }
}
