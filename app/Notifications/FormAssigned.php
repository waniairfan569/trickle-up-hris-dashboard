<?php

namespace App\Notifications;

use App\Models\CompanyForm;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FormAssigned extends Notification
{
    use Queueable;

    public function __construct(public CompanyForm $form) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'form_assigned',
            'title' => 'New form to complete',
            'message' => "You've been assigned the form “{$this->form->title}”.",
            'form_id' => $this->form->id,
            'url' => route('forms.fill', $this->form->id),
            'icon' => 'clipboard-list',
        ];
    }
}
