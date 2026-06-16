<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentSigningCompleted extends Notification
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
        $name = $this->documentRequest->template->name ?? 'A document';
        $declined = $this->documentRequest->status === 'declined';

        return [
            'type' => $declined ? 'document_declined' : 'document_completed',
            'title' => $declined ? 'Document declined' : 'Document completed',
            'message' => $declined
                ? "“{$name}” was declined."
                : "“{$name}” has been fully signed.",
            'request_id' => $this->documentRequest->id,
            'url' => route('documents.show', $this->documentRequest->id),
            'icon' => $declined ? 'x-circle' : 'check-circle',
        ];
    }
}
