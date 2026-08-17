<?php

namespace App\Notifications;

use App\Models\LeaveReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the employee when HR approves or declines their early-return request. */
class LeaveReturnReviewed extends Notification
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
        $approved = $r->status === 'approved';

        return [
            'type' => 'leave_return_reviewed',
            'urgent' => false,
            'leave_return_id' => $r->id,
            'title' => $approved ? 'Your early return was approved' : 'Your early return was declined',
            'message' => $approved
                ? "{$r->days_returned} day(s) credited back — you're expected back on " . optional($r->return_date)->format('d M Y') . '.'
                : ($r->review_note ?: 'Your request to return early was declined.'),
            'url' => route('time-off.index'),
            'icon' => $approved ? 'calendar-check' : 'calendar-x',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->leaveReturn;
        $approved = $r->status === 'approved';

        $mail = (new MailMessage)
            ->subject($approved ? '✅ Early return approved' : '🚫 Early return declined')
            ->greeting($approved ? 'Your early return is approved' : 'Your early return was declined');

        if ($approved) {
            $mail->line('**' . $r->days_returned . ' day(s)** have been credited back to your balance.')
                ->line('**Back at work on:** ' . optional($r->return_date)->format('d M Y'));
        } else {
            $mail->line('Your request to return early was not approved, so your original leave stands.')
                ->when($r->review_note, fn ($m) => $m->line("**Note:** {$r->review_note}"));
        }

        return $mail->action('View my time off', route('time-off.index'));
    }
}
