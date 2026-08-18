<?php

namespace App\Notifications;

use App\Models\HrDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HrDocumentSignatureRequested extends Notification
{
    use Queueable;

    public function __construct(public HrDocument $document) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $name = $this->document->template_name;

        return [
            'type'    => 'hr_document_signature_requested',
            'title'   => 'Signature requested',
            'message' => "Please review and sign “{$name}”.",
            'url'     => route('hr-documents.sign', $this->document->id),
            'icon'    => 'file-signature',
        ];
    }
}
