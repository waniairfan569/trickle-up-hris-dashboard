<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Send a notification to one or more users.
     *
     * @param  int|array  $userIds
     * @param  string     $type     e.g. time_off_requested, time_off_approved, attendance_approved
     * @param  string     $title
     * @param  string     $message
     * @param  array      $data     Extra payload stored in JSON column
     * @param  int        $companyId
     */
    public static function send(
        int|array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
        int $companyId = 0
    ): void {
        foreach ((array) $userIds as $userId) {
            $notification = Notification::create([
                'user_id'    => $userId,
                'company_id' => $companyId,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'data'       => $data,
            ]);

            try {
                broadcast(new NewNotification($notification));
            } catch (\Exception $e) {
                // Non-fatal — notification is persisted even if broadcast fails
                logger()->warning('[NotificationService] broadcast failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get all user IDs in a company that have a given permission key enabled.
     */
    public static function usersWithPermission(int $companyId, string $permission): array
    {
        return User::where('company_id', $companyId)
            ->whereHas('roles.permissions', fn($q) => $q->where('slug', $permission))
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get all super admin user IDs in a company.
     */
    public static function superAdmins(int $companyId): array
    {
        return User::where('company_id', $companyId)
            ->whereHas('roles', fn($q) => $q->where('slug', 'super_admin'))
            ->pluck('id')
            ->toArray();
    }
}
