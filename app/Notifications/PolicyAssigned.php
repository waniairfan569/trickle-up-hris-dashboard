<?php

namespace App\Notifications;

use App\Models\CompanyPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PolicyAssigned extends Notification
{
    use Queueable;

    public function __construct(public CompanyPolicy $policy, public bool $isReminder = false) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => $this->isReminder ? 'policy_reminder' : 'policy_assigned',
            'title' => $this->isReminder ? 'Policy acknowledgment reminder' : 'New policy to acknowledge',
            'message' => $this->isReminder
                ? "Reminder: please review and acknowledge “{$this->policy->title}”."
                : "A new policy has been assigned to you: “{$this->policy->title}”.",
            'policy_id' => $this->policy->id,
            'url' => route('policies.view', $this->policy->id),
            'icon' => 'book-text',
        ];
    }
}
