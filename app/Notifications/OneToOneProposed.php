<?php

namespace App\Notifications;

use App\Models\OneToOne;
use App\Models\User;
use App\Services\TimezoneService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Somebody has put a time forward and is waiting on the recipient.
 *
 * Sent to whoever owes the answer, never to both: the other side already knows,
 * because they are the one who proposed it.
 */
class OneToOneProposed extends Notification
{
    use Queueable;

    public function __construct(public OneToOne $meeting, public User $by) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $when = $this->when($notifiable);

        return (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line(($this->by->full_name ?? 'Somebody') . ' would like a one-to-one with you.')
            ->line("**{$this->meeting->subject}**")
            ->line("**When:** {$when}")
            ->when($this->meeting->location, fn ($m) => $m->line("**Where:** {$this->meeting->location}"))
            ->when($this->meeting->agenda, fn ($m) => $m->line($this->meeting->agenda))
            ->action('Agree or suggest another time', route('one-to-ones.show', $this->meeting))
            ->line('Nothing is booked until you answer, so it is worth doing either way.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'one_to_one_proposed',
            'title' => $this->subject(),
            'message' => ($this->by->full_name ?? 'Somebody') . ' — ' . $this->when($notifiable),
            'url' => route('one-to-ones.show', $this->meeting),
            'icon' => 'calendar-clock',
        ];
    }

    private function subject(): string
    {
        return 'A one-to-one is waiting on you: ' . $this->meeting->subject;
    }

    private function when($notifiable): string
    {
        $tz = app(TimezoneService::class);

        return $tz->toUserTime($this->meeting->scheduled_at, $notifiable)->format('D j M Y, H:i')
            . ' (' . $tz->abbreviation($notifiable) . ')';
    }
}
