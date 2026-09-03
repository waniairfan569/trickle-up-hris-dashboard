<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Daily admin reminder (in-app / bell): WFH-tomorrow or late-today list. */
class AdminDailyReminder extends Notification
{
    /**
     * @param  string  $kind  'wfh_tomorrow' | 'late_today'
     * @param  Collection  $people  employees on the list
     */
    public function __construct(public string $kind, public Collection $people)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $count = $this->people->count();
        $names = $this->people->map(fn ($u) => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')))->values()->all();

        [$title, $message] = $this->kind === 'wfh_tomorrow'
            ? ['Working from home tomorrow', "{$count} employee" . ($count === 1 ? '' : 's') . ' will be working from home tomorrow.']
            : ['Late arrivals today', "{$count} employee" . ($count === 1 ? '' : 's') . ' clocked in late today.'];

        return [
            'type' => 'admin_reminder',
            'kind' => $this->kind,
            'title' => $title,
            'message' => $message,
            'count' => $count,
            'employees' => $this->people->map(fn ($u) => [
                'id' => $u->id,
                'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
            ])->values()->all(),
            'names' => $names,
            'url' => route('admin.reminders'),
        ];
    }
}
