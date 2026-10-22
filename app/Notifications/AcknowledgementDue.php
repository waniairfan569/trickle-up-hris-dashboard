<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nudge somebody about something they were asked to read and acknowledge and
 * have not.
 *
 * Takes either a policy or a company document — both answer the same questions
 * now (NeedsAcknowledgement), so this does not need to know which it has. The
 * category follows from the kind, so it lands under the toggle the employee
 * already recognises rather than a new one.
 */
class AcknowledgementDue extends Notification
{
    use Queueable;

    public function __construct(public Model $subject) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /** Which Settings toggle governs this one — see NotificationCategories. */
    public function notificationCategory(): string
    {
        return $this->subject instanceof \App\Models\CompanyPolicy ? 'policies' : 'documents';
    }

    public function toMail($notifiable): MailMessage
    {
        $title = (string) $this->subject->title;
        $due = $this->subject->acknowledgementDueAt();

        $mail = (new MailMessage)
            ->subject("Still to confirm you have read: {$title}")
            ->greeting('Hello ' . ($notifiable->first_name ?? '') . ' 👋')
            ->line("**{$title}** is waiting for you to confirm you have read it.");

        if ($due) {
            $mail->line($due->isPast()
                ? "This was due by {$due->format('j F Y')}."
                : "Please do this by {$due->format('j F Y')}.");
        }

        return $mail
            ->action('Read and confirm', $this->subject->acknowledgementUrl())
            ->line('It only takes a moment — open it, read it, and confirm at the bottom.');
    }

    public function toDatabase($notifiable)
    {
        $title = (string) $this->subject->title;

        return [
            'type' => 'acknowledgement_due',
            'title' => 'Still to acknowledge',
            'message' => "“{$title}” is waiting for you to confirm you have read it.",
            'subject_type' => $this->subject->getMorphClass(),
            'subject_id' => $this->subject->id,
            'url' => $this->subject->acknowledgementUrl(),
            'icon' => 'book-open-check',
        ];
    }
}
