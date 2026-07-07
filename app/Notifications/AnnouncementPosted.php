<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPosted extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    /** Bell only — the email is sent once (BCC) from the controller to avoid N sends. */
    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'announcement',
            'urgent' => true,
            'title' => '📢 ' . $this->announcement->title,
            'message' => Str::limit(strip_tags($this->announcement->body), 120),
            'url' => route('dashboard'),
            'icon' => 'megaphone',
        ];
    }
}
