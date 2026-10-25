<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Checks that have lapsed, or are about to.
 *
 * One message listing all of them rather than one per check: somebody opening
 * their email wants the morning's work, not twenty separate reminders about
 * the same thing.
 */
class EmployeeChecksDue extends Notification
{
    use Queueable;

    public function __construct(public Collection $expired, public Collection $expiring, public int $days) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋');

        if ($this->expired->isNotEmpty()) {
            $mail->line('**Lapsed already — somebody may be working without a valid check:**');
            foreach ($this->expired->take(20) as $c) {
                $mail->line("• {$c->employee?->full_name}: {$c->typeLabel()}, expired {$c->expires_on->format('j M Y')}");
            }
        }

        if ($this->expiring->isNotEmpty()) {
            $mail->line("**Lapsing within {$this->days} days:**");
            foreach ($this->expiring->take(20) as $c) {
                $mail->line("• {$c->employee?->full_name}: {$c->typeLabel()}, until {$c->expires_on->format('j M Y')}");
            }
        }

        return $mail
            ->action('Open the checks', route('employee-checks.index'))
            ->line('A check that has lapsed usually has to be done again before the person keeps working.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'employee_checks_due',
            'title' => $this->subject(),
            'message' => $this->expired->count() . ' lapsed, ' . $this->expiring->count()
                . " lapsing within {$this->days} days.",
            'url' => route('employee-checks.index'),
            'icon' => 'shield-alert',
        ];
    }

    private function subject(): string
    {
        return $this->expired->isNotEmpty()
            ? $this->expired->count() . ' check(s) have lapsed'
            : $this->expiring->count() . ' check(s) lapsing soon';
    }
}
