<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dunning: sent to a workspace's owner(s) when a Stripe subscription payment
 * fails, so they can update their card before the workspace is suspended.
 */
class SubscriptionPaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public ?string $amount = null,
        public string $currency = '',
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'urgent' => true,
            'title' => 'Payment failed — update your card',
            'message' => 'Your subscription payment could not be processed. Please update your payment method to avoid losing access.',
            'url' => route('billing.index'),
            'icon' => 'credit-card',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $amount = $this->amount ? trim($this->currency . ' ' . $this->amount) : null;

        return (new MailMessage)
            ->error()
            ->subject('⚠️ Payment failed for your ' . config('legal.company') . ' subscription')
            ->greeting('Action needed')
            ->line('We were unable to process the latest payment' . ($amount ? " of {$amount}" : '') . " for your workspace “{$this->tenant->displayName()}”.")
            ->line('Please update your payment method to keep your subscription active. If the payment keeps failing, access to your workspace may be suspended.')
            ->action('Update payment method', route('billing.index'))
            ->line('If you have already updated your card, you can ignore this message.');
    }
}
