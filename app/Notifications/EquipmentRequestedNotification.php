<?php

namespace App\Notifications;

use App\Models\EquipmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to admins/HR when an employee requests to take equipment home. */
class EquipmentRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public EquipmentRequest $equipmentRequest) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        $name = $this->equipmentRequest->employee->full_name ?? 'An employee';

        return [
            'type' => 'equipment_requested',
            'urgent' => false,
            'title' => "{$name} wants to take equipment home",
            'message' => "{$name} requested to take “{$this->equipmentRequest->equipment_name}” home. Review and approve or decline.",
            'equipment' => $this->equipmentRequest->equipment_name,
            'url' => route('equipment.admin'),
            'icon' => 'package',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->equipmentRequest;
        $name = $r->employee->full_name ?? 'An employee';

        $mail = (new MailMessage)
            ->subject("📦 Equipment request from {$name} — {$r->equipment_name}")
            ->greeting('New equipment request')
            ->line("**{$name}** wants to take company equipment home.")
            ->line("**Equipment:** {$r->equipment_name}")
            ->line("**Reason:** {$r->reason}");

        if ($r->expected_return_date) {
            $mail->line('**Expected return:** ' . $r->expected_return_date->format('D, d M Y'));
        }

        return $mail
            ->action('Review request', route('equipment.admin'))
            ->line('Request ' . $r->request_number . '.');
    }
}
