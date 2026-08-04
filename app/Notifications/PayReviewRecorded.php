<?php

namespace App\Notifications;

use App\Models\PayReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an employee that a pay review has been recorded for them — email + in-app.
 */
class PayReviewRecorded extends Notification
{
    use Queueable;

    public PayReview $payReview;

    public function __construct(PayReview $payReview)
    {
        $this->payReview = $payReview;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    private function label(): string
    {
        return $this->payReview->pay_type === 'hourly' ? 'rate' : 'salary';
    }

    private function money($amount): string
    {
        return ($this->payReview->currency ?: 'PKR') . ' ' . number_format((float) $amount, 0);
    }

    public function toMail($notifiable): MailMessage
    {
        $pr = $this->payReview;
        $effective = optional($pr->effective_date)->format('j F Y');
        $increment = (float) $pr->increment_amount;

        $mail = (new MailMessage)
            ->subject('Your pay has been reviewed')
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line('A pay review has been recorded for you.')
            ->line('**New ' . $this->label() . ':** ' . $this->money($pr->new_salary) . ($effective ? " (effective {$effective})" : ''));

        if ($increment > 0) {
            $pct = $pr->increment_percent !== null
                ? ' (+' . rtrim(rtrim(number_format((float) $pr->increment_percent, 2), '0'), '.') . '%)'
                : '';
            $mail->line('That’s an increase of ' . $this->money($increment) . $pct . ' 🎉');
        }

        if ($pr->reason) {
            $mail->line('Reason: ' . $pr->reason);
        }

        return $mail
            ->action('View my compensation', route('employees.profile', $pr->user_id))
            ->line('If you have any questions, please speak with HR.');
    }

    public function toDatabase($notifiable)
    {
        $pr = $this->payReview;

        return [
            'type' => 'pay_review',
            'title' => 'Pay review recorded',
            'message' => 'Your pay has been reviewed — new ' . $this->label() . ' ' . $this->money($pr->new_salary) . '.',
            'url' => route('employees.profile', $pr->user_id),
            'icon' => 'badge-dollar-sign',
        ];
    }
}
