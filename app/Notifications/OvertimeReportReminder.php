<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Recurring nudge to generate the approved-overtime report for payroll. */
class OvertimeReportReminder extends Notification
{
    use Queueable;

    public function __construct(public string $scheduleLabel) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type'    => 'overtime_report_reminder',
            'title'   => '⏰ Overtime report due',
            'message' => 'Time to generate the approved-overtime report for payroll.',
            'url'     => route('overtime-report.index'),
            'icon'    => 'file-bar-chart-2',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⏰ Overtime report reminder')
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line('This is your scheduled reminder to generate the approved-overtime report for payroll.')
            ->action('Open overtime report', route('overtime-report.index'))
            ->line('Schedule: ' . $this->scheduleLabel)
            ->line('This is an automated message from Trickle Hub.');
    }
}
