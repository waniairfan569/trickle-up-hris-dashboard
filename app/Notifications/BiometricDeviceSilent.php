<?php

namespace App\Notifications;

use App\Models\ZktecoDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A biometric device has stopped reaching the server, or has started again.
 *
 * This is the notice that was missing when one went quiet mid-morning and the
 * first anybody knew was the next day's empty dashboard. Punches are still
 * being stored on the device itself, so the message says so — the urgent part
 * is the connection, not the data.
 */
class BiometricDeviceSilent extends Notification
{
    use Queueable;

    public function __construct(
        public ZktecoDevice $device,
        public bool $recovered = false,
        public ?int $silentMinutes = null,
    ) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->device->name ?: ('Device ' . $this->device->serial_number);

        if ($this->recovered) {
            return (new MailMessage)
                ->subject("Back online: {$name}")
                ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
                ->line("**{$name}** is reaching the server again.")
                ->line('Anything it stored while it was away is sent automatically, so the missing punches should appear on their own within a few minutes.')
                ->action('Check attendance', route('zkteco.dashboard'));
        }

        return (new MailMessage)
            ->subject("No contact from {$name}")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("**{$name}** has not reached the server for {$this->forHumans()}.")
            ->line('People can still clock in — the device accepts fingerprints and stores them itself — but nothing is reaching attendance, so today will look empty until it reconnects.')
            ->line('Usual causes: the device has lost power or network, or its server settings have changed.')
            ->action('Look at the devices', route('zkteco.dashboard'))
            ->line('Nothing is lost. Stored punches are sent as soon as it reconnects.');
    }

    public function toDatabase($notifiable)
    {
        $name = $this->device->name ?: ('Device ' . $this->device->serial_number);

        return [
            'type' => $this->recovered ? 'biometric_device_back' : 'biometric_device_silent',
            'title' => $this->recovered ? 'Biometric device back online' : 'Biometric device not reporting',
            'message' => $this->recovered
                ? "{$name} is reaching the server again."
                : "{$name} has not reached the server for {$this->forHumans()}. Clock-ins are not arriving.",
            'url' => route('zkteco.dashboard'),
            'icon' => $this->recovered ? 'plug-zap' : 'wifi-off',
        ];
    }

    private function forHumans(): string
    {
        $minutes = (int) ($this->silentMinutes ?? 0);

        if ($minutes < 120) {
            return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        $hours = intdiv($minutes, 60);

        return $hours < 48
            ? $hours . ' hour' . ($hours === 1 ? '' : 's')
            : intdiv($hours, 24) . ' days';
    }
}
