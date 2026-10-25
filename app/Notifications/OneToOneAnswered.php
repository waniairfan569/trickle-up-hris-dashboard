<?php

namespace App\Notifications;

use App\Models\OneToOne;
use App\Models\User;
use App\Services\TimezoneService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The answer to a proposal, or the record of what was agreed in the room.
 *
 * One class for every outcome rather than four, because the recipient wants
 * the same three facts each time — what happened to the booking, when it was
 * for, and where to go to read the rest.
 */
class OneToOneAnswered extends Notification
{
    use Queueable;

    /** @param string $outcome confirmed|declined|cancelled|held */
    public function __construct(public OneToOne $meeting, public User $by, public string $outcome) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $who = $this->by->full_name ?? 'Somebody';

        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line(match ($this->outcome) {
                'confirmed' => "{$who} has agreed the time for your one-to-one.",
                'declined' => "{$who} cannot make the time proposed for your one-to-one.",
                'cancelled' => "{$who} has cancelled your one-to-one.",
                'held' => "{$who} has written up your one-to-one.",
                default => "{$who} has updated your one-to-one.",
            })
            ->line("**{$this->meeting->subject}**")
            ->line('**When:** ' . $this->when($notifiable));

        // The reason sits in the trail, and for a decline or a cancellation it
        // is the only part that tells the recipient what to do next.
        if (in_array($this->outcome, ['declined', 'cancelled'], true)) {
            $last = $this->meeting->events()->latest('id')->first();
            if ($last && $last->note) {
                $mail->line('**They said:** ' . $last->note);
            }
        }

        if ($this->outcome === 'held' && $this->meeting->outcome) {
            $mail->line('**What was agreed:** ' . $this->meeting->outcome);
        }

        return $mail->action('Open it', route('one-to-ones.show', $this->meeting));
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'one_to_one_' . $this->outcome,
            'title' => $this->subject(),
            'message' => ($this->by->full_name ?? 'Somebody') . ' — ' . $this->when($notifiable),
            'url' => route('one-to-ones.show', $this->meeting),
            'icon' => match ($this->outcome) {
                'confirmed' => 'calendar-check',
                'declined', 'cancelled' => 'calendar-x',
                'held' => 'clipboard-check',
                default => 'calendar',
            },
        ];
    }

    private function subject(): string
    {
        return match ($this->outcome) {
            'confirmed' => 'Agreed: ' . $this->meeting->subject,
            'declined' => 'Declined: ' . $this->meeting->subject,
            'cancelled' => 'Cancelled: ' . $this->meeting->subject,
            'held' => 'Written up: ' . $this->meeting->subject,
            default => 'Updated: ' . $this->meeting->subject,
        };
    }

    private function when($notifiable): string
    {
        $tz = app(TimezoneService::class);

        return $tz->toUserTime($this->meeting->scheduled_at, $notifiable)->format('D j M Y, H:i')
            . ' (' . $tz->abbreviation($notifiable) . ')';
    }
}
