<?php

namespace App\Services;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeAccessService
{
    /**
     * Get the reporting line of a manager (direct and indirect reports recursively).
     * Returns a collection of User models.
     */
    public function getReportingLine(User $manager): Collection
    {
        $reports = collect();
        $this->collectReports($manager, $reports);
        return $reports;
    }

    /**
     * Recursively collect direct and indirect reports in a cycle-safe manner.
     */
    private function collectReports(User $user, Collection &$reports, array &$visited = []): void
    {
        if (in_array($user->id, $visited)) {
            return;
        }
        $visited[] = $user->id;

        $user->loadMissing('directReports');

        foreach ($user->directReports as $report) {
            if (!$reports->contains('id', $report->id)) {
                $reports->push($report);
                $this->collectReports($report, $reports, $visited);
            }
        }
    }

    /**
     * Check if a target user is in the reporting line of a manager.
     */
    public function isInReportingLine(User $manager, User $target): bool
    {
        return $manager->canManage($target);
    }

    /**
     * Get an Employee query filtered by the authenticated user's access scope.
     */
    public function getScopedEmployeeQuery(User $auth): Builder
    {
        $auth->loadMissing('roles');

        return Employee::query();
    }
}
