<?php

namespace App\Notifications;

use App\Models\ProfileChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** HR has approved or turned down a change somebody asked for. */
class ProfileChangeReviewed extends Notification
{
    use Queueable;

    public function __construct(public ProfileChangeRequest $request) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $approved = $this->request->status === 'approved';
        $name = $this->request->label();
        $note = trim((string) $this->request->review_note);

        return [
            'type'    => 'profile_change_reviewed',
            'title'   => $approved ? 'Your record change was approved' : 'Your record change was not approved',
            'message' => ($approved
                ? "Your change to “{$name}” has been applied."
                : "Your change to “{$name}” was not applied.")
                . ($note !== '' ? " {$note}" : ''),
            'url'     => route('employees.profile', $this->request->user_id),
            'icon'    => $approved ? 'check-circle' : 'x-circle',
        ];
    }
}
