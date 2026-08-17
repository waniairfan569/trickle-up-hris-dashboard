<?php

namespace App\Notifications;

use App\Models\LeaveReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to HR / managers when an employee asks to return early from a leave. */
class LeaveReturnRequested extends Notification
{
    use Queueable;

    public function __construct(public LeaveReturn $leaveReturn) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $r = $this->leaveReturn;
        $name = optional($r->employee)->full_name ?? 'An employee';

        return [
            'type' => 'leave_return_requested',
            'urgent' => false,
            'leave_return_id' => $r->id,
            'title' => "Early-return request from {$name}",
            'message' => "Returning {$r->days_returned} day(s) early on " . optional($r->return_date)->format('d M Y') . ' — needs approval.',
            'url' => route('time-off.index', ['tab' => 'team_requests']),
            'icon' => 'calendar-check',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->leaveReturn;
        $name = optional($r->employee)->full_name ?? 'An employee';
        $policy = optional(optional($r->request)->policy)->name ?? 'leave';

        return (new MailMessage)
            ->subject("↩️ {$name} wants to return early from leave")
            ->greeting('Early-return request')
            ->line("**{$name}** has asked to return early from their {$policy}.")
            ->line('**Back on:** ' . optional($r->return_date)->format('d M Y'))
            ->line("**Days to credit back:** {$r->days_returned}")
            ->when($r->reason, fn ($m) => $m->line("**Reason:** {$r->reason}"))
            ->action('Review request', route('time-off.index', ['tab' => 'team_requests']));
    }
}
