<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentSignatureRequested extends Notification
{
    use Queueable;

    public DocumentRequest $documentRequest;

    public function __construct(DocumentRequest $documentRequest)
    {
        $this->documentRequest = $documentRequest;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->documentRequest->template->name ?? 'a document';
        $sender = optional($this->documentRequest->creator)->full_name;

        $mail = (new MailMessage)
            ->subject("✍️ Please sign: {$name}")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("You've been requested to sign **{$name}**" . ($sender ? " by {$sender}" : '') . '.')
            ->action('Review & sign', route('documents.sign', $this->documentRequest->id))
            ->line('Please review the document and add your signature.');

        return $mail;
    }

    public function toDatabase($notifiable)
    {
        $name = $this->documentRequest->template->name ?? 'a document';

        return [
            'type' => 'document_signature_requested',
            'title' => 'Signature requested',
            'message' => "You're requested to sign “{$name}”.",
            'request_id' => $this->documentRequest->id,
            'url' => route('documents.sign', $this->documentRequest->id),
            'icon' => 'file-signature',
        ];
    }
}
