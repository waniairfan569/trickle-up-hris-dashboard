<?php

namespace App\Notifications;

use App\Models\CompanyForm;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FormReviewAccessGranted extends Notification
{
    use Queueable;

    public function __construct(public CompanyForm $form) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'form_review_access',
            'title' => 'You can now review a form',
            'message' => 'You were given access to review responses for "' . $this->form->title . '".',
            'url' => route('company-forms.responses', $this->form->id),
            'icon' => 'clipboard-check',
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You can now review: ' . $this->form->title)
            ->greeting('Hi ' . ($notifiable->first_name ?? 'there') . ',')
            ->line('You have been given access to review responses for "' . $this->form->title . '".')
            ->line('You can view submissions and approve, reject, or leave a suggestion.')
            ->action('Open responses', route('company-forms.responses', $this->form->id))
            ->line('This is an automated message from Trickle Hub.');
    }
}
