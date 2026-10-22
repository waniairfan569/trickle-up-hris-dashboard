<?php

namespace App\Notifications;

use App\Models\ProfileChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** An employee wants to change something on their record that HR must approve. */
class ProfileChangeRequested extends Notification
{
    use Queueable;

    public function __construct(public ProfileChangeRequest $request) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $who = $this->request->employee?->full_name ?? 'An employee';

        return [
            'type'    => 'profile_change_requested',
            'title'   => 'A record change needs approving',
            'message' => "{$who} wants to change “{$this->request->label()}” on their record.",
            'url'     => route('profile-changes.index'),
            'icon'    => 'user-pen',
        ];
    }
}
