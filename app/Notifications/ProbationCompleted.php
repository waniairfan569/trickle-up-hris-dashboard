<?php

namespace App\Notifications;

use App\Models\Probation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the employee AND to HR/admins when a probation period completes
 * successfully. The copy adapts to whoever is receiving it.
 */
class ProbationCompleted extends Notification
{
    use Queueable;

    public Probation $probation;

    public function __construct(Probation $probation)
    {
        $this->probation = $probation;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    private function isEmployee($notifiable): bool
    {
        return (int) $notifiable->id === (int) $this->probation->user_id;
    }

    public function toMail($notifiable): MailMessage
    {
        $employee = $this->probation->employee;
        $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'The employee';
        $ended = optional($this->probation->end_date)->format('j F Y');

        if ($this->isEmployee($notifiable)) {
            return (new MailMessage)
                ->subject('🎉 Congratulations — your probation is complete!')
                ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 🎉')
                ->line("Great news — you've **successfully completed your probation period**" . ($ended ? " as of {$ended}" : '') . '.')
                ->line('Welcome aboard as a confirmed member of the team!')
                ->action('View your profile', route('employees.profile', $this->probation->user_id));
        }

        return (new MailMessage)
            ->subject("✅ Probation completed: {$fullName}")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("**{$fullName}** has successfully completed their probation period" . ($ended ? " (ended {$ended})" : '') . '.')
            ->action('View employee', route('employees.profile', $this->probation->user_id));
    }

    public function toDatabase($notifiable)
    {
        $employee = $this->probation->employee;
        $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: 'The employee';

        if ($this->isEmployee($notifiable)) {
            return [
                'type' => 'probation_completed',
                'title' => 'Probation completed 🎉',
                'message' => 'Congratulations — you have successfully completed your probation period.',
                'url' => route('employees.profile', $this->probation->user_id),
                'icon' => 'party-popper',
            ];
        }

        return [
            'type' => 'probation_completed',
            'title' => 'Probation completed',
            'message' => "{$fullName} has successfully completed their probation period.",
            'url' => route('employees.profile', $this->probation->user_id),
            'icon' => 'check-circle',
        ];
    }
}
