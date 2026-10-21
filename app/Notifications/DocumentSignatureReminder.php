<?php

namespace App\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nudge the current signer when a document has sat unsigned for 24+ hours.
 * Mirrors DocumentSignatureRequested but is worded as a reminder.
 */
class DocumentSignatureReminder extends Notification
{
    use Queueable;

    public Model $documentRequest;

    public function __construct(Model $documentRequest)
    {
        $this->documentRequest = $documentRequest;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->documentRequest->signingTitle();
        $sender = optional($this->documentRequest->creator)->full_name;

        return (new MailMessage)
            ->subject("⏰ Reminder — please sign: {$name}")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("Just a reminder that **{$name}**" . ($sender ? " from {$sender}" : '') . ' is still waiting for your signature.')
            ->line('It was sent to you more than 24 hours ago and hasn’t been signed yet.')
            ->action('Review & sign now', $this->documentRequest->signingUrl())
            ->line('Please take a moment to review the document and add your signature.');
    }

    public function toDatabase($notifiable)
    {
        $name = $this->documentRequest->signingTitle();

        return [
            'type' => 'document_signature_reminder',
            'title' => 'Reminder: signature needed',
            'message' => "“{$name}” is still awaiting your signature.",
            'request_id' => $this->documentRequest->id,
            'url' => $this->documentRequest->signingUrl(),
            'icon' => 'clock',
        ];
    }
}
