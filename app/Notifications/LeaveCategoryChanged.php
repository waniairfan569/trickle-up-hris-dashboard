<?php

namespace App\Notifications;

use App\Models\TimeOffRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an employee when an admin moves one of their time-off requests to a
 * different leave category (policy) — e.g. Unplanned → Planned.
 */
class LeaveCategoryChanged extends Notification
{
    use Queueable;

    public function __construct(
        public TimeOffRequest $request,
        public string $fromPolicy,
        public string $toPolicy,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $range = $this->request->start_date->format('d M Y') . ' – ' . $this->request->end_date->format('d M Y');

        return (new MailMessage)
            ->subject("Leave category updated: {$this->fromPolicy} → {$this->toPolicy}")
            ->greeting("Hi {$notifiable->first_name},")
            ->line("Your time-off for {$range} has been moved from “{$this->fromPolicy}” to “{$this->toPolicy}” by HR.")
            ->line('Your leave balances have been adjusted accordingly.')
            ->action('View my time off', route('time-off.index'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => "Leave category changed to {$this->toPolicy}",
            'message' => "Your leave ({$this->request->start_date->format('d M Y')}) was moved from “{$this->fromPolicy}” to “{$this->toPolicy}”.",
            'url' => route('time-off.index'),
            'icon' => 'refresh-cw',
        ];
    }
}
