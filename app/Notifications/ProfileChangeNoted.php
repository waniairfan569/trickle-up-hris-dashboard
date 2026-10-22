<?php

namespace App\Notifications;

use App\Models\ProfileField;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Somebody changed something on their own record that HR asked to hear about —
 * a medical condition, an emergency contact, a bank detail.
 *
 * The change is already saved; this exists so it is not silent.
 */
class ProfileChangeNoted extends Notification
{
    use Queueable;

    public function __construct(
        public User $employee,
        public ProfileField $field,
        public ?string $oldValue,
        public ?string $newValue,
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $name = $this->field->name ?: $this->field->key;

        // Private fields are reported as having changed, not quoted. A medical
        // condition is not something to copy into everyone's notification list.
        $private = $this->field->visibility === 'private' || $this->field->is_encrypted;

        $message = $private
            ? "{$this->employee->full_name} updated “{$name}” on their record."
            : "{$this->employee->full_name} changed “{$name}” from “{$this->oldValue}” to “{$this->newValue}”.";

        return [
            'type'    => 'profile_change_noted',
            'title'   => 'A record was updated',
            'message' => $message,
            'url'     => route('employees.profile', $this->employee->id),
            'icon'    => 'user-round-cog',
        ];
    }
}
