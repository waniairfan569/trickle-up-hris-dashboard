<?php

namespace App\Notifications;

use App\Models\EquipmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the employee when their equipment request is approved or rejected. */
class EquipmentRequestReviewedNotification extends Notification
{
    use Queueable;

    public function __construct(public EquipmentRequest $equipmentRequest) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    private function approved(): bool
    {
        return $this->equipmentRequest->status === 'approved';
    }

    public function toDatabase($notifiable)
    {
        $r = $this->equipmentRequest;
        $ok = $this->approved();

        return [
            'type' => 'equipment_reviewed',
            'urgent' => false,
            'title' => $ok
                ? "✅ Approved: {$r->equipment_name} to take home"
                : "🚫 Declined: {$r->equipment_name}",
            'message' => ($ok ? 'Your equipment request was approved.' : 'Your equipment request was declined.')
                . ($r->review_note ? ' Note: ' . $r->review_note : ''),
            'equipment' => $r->equipment_name,
            'note' => $r->review_note,
            'url' => route('equipment.index'),
            'icon' => $ok ? 'package-check' : 'package-x',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $r = $this->equipmentRequest;
        $ok = $this->approved();

        $mail = (new MailMessage)
            ->subject(($ok ? '✅ Approved' : '🚫 Declined') . " — {$r->equipment_name}")
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line($ok
                ? "Your request to take **{$r->equipment_name}** home has been approved."
                : "Your request to take **{$r->equipment_name}** home has been declined.");

        if ($r->review_note) {
            $mail->line('Note from the reviewer: "' . $r->review_note . '"');
        }
        if ($ok && $r->expected_return_date) {
            $mail->line('Please return it by ' . $r->expected_return_date->format('D, d M Y') . '.');
        }

        return $mail
            ->action('View my requests', route('equipment.index'))
            ->line('Request ' . $r->request_number . '.');
    }
}
