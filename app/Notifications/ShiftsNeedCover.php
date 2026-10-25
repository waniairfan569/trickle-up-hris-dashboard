<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Shifts in the days ahead whose person is on approved leave.
 *
 * Sent as one message covering everything newly found, not one per hole: a
 * fortnight booked off is one decision and should read as one, and a message
 * per day would be eleven emails about the same absence.
 *
 * Each hole is mentioned once. If it is still open tomorrow nothing is sent
 * again, because a daily repeat of the same list is how a useful notice turns
 * into one nobody opens.
 */
class ShiftsNeedCover extends Notification
{
    use Queueable;

    /** @param Collection<int,object> $gaps rows from ShiftCoverageService */
    public function __construct(public Collection $gaps) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $count = $this->gaps->count();
        $people = $this->gaps->pluck('user.id')->unique()->count();

        $mail = (new MailMessage)
            ->subject($count === 1 ? 'A shift needs covering' : "{$count} shifts need covering")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line($count === 1
                ? 'One rostered shift has nobody on it, because the person is on approved leave.'
                : "{$count} rostered shifts have nobody on them, across {$people} " . ($people === 1 ? 'person' : 'people') . ' on approved leave.');

        foreach ($this->gaps->sortBy(fn ($g) => $g->date->toDateString())->take(20) as $gap) {
            $mail->line('**' . $gap->date->format('D j M') . '** · ' . $gap->shift->name
                . ' · ' . trim(($gap->user->first_name ?? '') . ' ' . ($gap->user->last_name ?? ''))
                . ' (' . $gap->reason . ')');
        }

        if ($count > 20) {
            $mail->line('…and ' . ($count - 20) . ' more.');
        }

        return $mail
            ->action('Fill them in', route('shifts.coverage'))
            ->line('Putting somebody on a day here only changes that day. Their usual shift is untouched.');
    }

    public function toDatabase($notifiable)
    {
        $count = $this->gaps->count();
        $first = $this->gaps->sortBy(fn ($g) => $g->date->toDateString())->first();

        return [
            'type' => 'shifts_need_cover',
            'title' => $count === 1 ? 'A shift needs covering' : "{$count} shifts need covering",
            'message' => $count === 1
                ? $first->shift->name . ' on ' . $first->date->format('D j M')
                    . ' — ' . ($first->user->first_name ?? 'somebody') . ' is on ' . strtolower($first->reason) . '.'
                : "From {$first->date->format('D j M')}, {$count} rostered shifts have nobody on them.",
            'url' => route('shifts.coverage'),
            'icon' => 'user-round-x',
        ];
    }
}
