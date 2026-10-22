<?php

namespace App\Notifications;

use App\Models\HrDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A document has been put on somebody's record and there is nothing for them
 * to do about it — no signature was asked for.
 *
 * The signing notifications all say "please sign this", which is wrong for a
 * return-to-work record or a confirmation note. Those still need telling, or
 * the document is filed and the person it is about never knows.
 */
class HrDocumentIssued extends Notification
{
    use Queueable;

    public function __construct(public HrDocument $document) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /** Filed under the same toggle as the rest of their documents. */
    public function notificationCategory(): string
    {
        return 'documents';
    }

    public function toMail($notifiable): MailMessage
    {
        $name = $this->document->template_name;

        return (new MailMessage)
            ->subject("Added to your record: {$name}")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("**{$name}** has been added to your record.")
            ->line('There is nothing to sign — this is for your information, and your copy is attached to your documents.')
            ->action('Read it', route('hr-documents.my-pdf', $this->document->id));
    }

    public function toDatabase($notifiable)
    {
        $name = $this->document->template_name;

        return [
            'type'    => 'hr_document_issued',
            'title'   => 'Added to your record',
            'message' => "“{$name}” has been added to your record. Nothing to sign.",
            'url'     => route('hr-documents.my-pdf', $this->document->id),
            'icon'    => 'file-check',
        ];
    }
}
