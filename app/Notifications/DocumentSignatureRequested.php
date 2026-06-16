<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
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
        return ['database'];
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
