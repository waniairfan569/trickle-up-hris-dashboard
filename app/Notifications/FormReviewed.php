<?php

namespace App\Notifications;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FormReviewed extends Notification
{
    use Queueable;

    public function __construct(public FormSubmission $submission) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        $formTitle = optional($this->submission->form)->title ?? 'your form';
        $status = $this->submission->review_status;
        $approved = $status === 'approved';

        $title = $approved ? "✅ {$formTitle} approved" : "❌ {$formTitle} needs changes";
        $message = $approved
            ? "Your response to “{$formTitle}” was approved."
            : "Your response to “{$formTitle}” was rejected. Check HR's suggestion.";
        if ($this->submission->review_note) {
            $message .= ' — "' . \Illuminate\Support\Str::limit($this->submission->review_note, 80) . '"';
        }

        return [
            'type' => 'form_reviewed',
            'urgent' => !$approved,
            'title' => $title,
            'message' => $message,
            'url' => route('forms.fill', $this->submission->form_id),
            'icon' => $approved ? 'check-circle' : 'x-circle',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $formTitle = optional($this->submission->form)->title ?? 'your form';
        $approved = $this->submission->review_status === 'approved';

        $mail = (new MailMessage)
            ->subject(($approved ? '✅ Approved: ' : '❌ Needs changes: ') . $formTitle)
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line($approved
                ? "Your response to \"{$formTitle}\" has been approved."
                : "Your response to \"{$formTitle}\" was rejected and needs changes.");

        if ($this->submission->review_note) {
            $mail->line('HR suggestion: "' . $this->submission->review_note . '"');
        }

        return $mail->action('View your submission', route('forms.fill', $this->submission->form_id))
            ->line('This is an automated message from TrickleUp Hub.');
    }
}
