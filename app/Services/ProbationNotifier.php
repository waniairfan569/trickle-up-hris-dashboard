<?php

namespace App\Services;

use App\Models\Probation;
use App\Models\User;
use App\Notifications\ProbationCompleted;

class ProbationNotifier
{
    /**
     * Notify the employee AND every HR / super admin that a probation has
     * completed successfully. Idempotent — stamps completion_notified_at and
     * does nothing if it has already been sent. Returns true if it sent now.
     */
    public function notifyCompletion(Probation $probation): bool
    {
        if ($probation->completion_notified_at || $probation->status === 'failed') {
            return false;
        }

        $employee = $probation->employee;
        if (!$employee) {
            return false;
        }

        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['hr_admin', 'super_admin'])
                ->orWhereIn('name', ['hr_admin', 'super_admin']);
        })->where('account_status', '!=', 'deactivated')->get();

        $recipients = collect([$employee])->concat($admins)->unique('id');

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new ProbationCompleted($probation));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $probation->forceFill(['completion_notified_at' => now()])->save();

        return true;
    }
}
