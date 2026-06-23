<?php

namespace App\Notifications;

use App\Models\CompanyForm;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FormSubmitted extends Notification
{
    use Queueable;

    public function __construct(public CompanyForm $form, public ?string $submitterName = null) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $who = $this->form->is_anonymous || !$this->submitterName ? 'An employee' : $this->submitterName;

        return [
            'type' => 'form_submitted',
            'title' => 'New form response',
            'message' => "{$who} submitted “{$this->form->title}”.",
            'form_id' => $this->form->id,
            'url' => route('company-forms.responses', $this->form->id),
            'icon' => 'clipboard-check',
        ];
    }
}
