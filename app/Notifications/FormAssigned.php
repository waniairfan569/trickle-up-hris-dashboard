<?php

namespace App\Notifications;

use App\Models\CompanyForm;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FormAssigned extends Notification
{
    use Queueable;

    public function __construct(public CompanyForm $form, public ?string $period = null) {}

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable)
    {
        $monthLabel = $this->period ? CompanyForm::periodLabel($this->period) : null;

        return [
            'type' => 'form_assigned',
            'title' => $monthLabel ? "New form for {$monthLabel}" : 'New form to complete',
            'message' => $monthLabel
                ? "The form “{$this->form->title}” is open for {$monthLabel} — please submit it."
                : "You've been assigned the form “{$this->form->title}”.",
            'form_id' => $this->form->id,
            'url' => route('forms.fill', $this->form->id),
            'icon' => 'clipboard-list',
        ];
    }

    public function toMail($notifiable)
    {
        $monthLabel = $this->period ? CompanyForm::periodLabel($this->period) : null;

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($monthLabel ? "New form for {$monthLabel}: {$this->form->title}" : "New form to complete: {$this->form->title}")
            ->greeting("Hi {$notifiable->first_name},")
            ->line($monthLabel
                ? "The form “{$this->form->title}” is now open for {$monthLabel}. Please fill it in and submit."
                : "You've been assigned the form “{$this->form->title}”. Please fill it in and submit.")
            ->action('Open the form', route('forms.fill', $this->form->id))
            ->line('Thank you.');
    }
}
