<?php

namespace App\Notifications;

use App\Models\HrDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HrDocumentSigned extends Notification
{
    use Queueable;

    public function __construct(public HrDocument $document, public string $signerName) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $name = $this->document->template_name;
        $fully = $this->document->fully_signed;

        return [
            'type'    => 'hr_document_signed',
            'title'   => $fully ? 'Document fully signed' : 'Document signed',
            'message' => $fully
                ? "“{$name}” is now fully signed."
                : "{$this->signerName} signed “{$name}”.",
            'url'     => route('hr-documents.show', $this->document->id),
            'icon'    => 'check-circle',
        ];
    }
}
