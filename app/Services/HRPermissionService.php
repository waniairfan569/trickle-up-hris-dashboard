<?php

namespace App\Services;

use App\Models\User;

class HRPermissionService
{
    /**
     * Determine if a user can view another employee's profile.
     */
    public function canViewEmployee(User $auth, User $target): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if ($auth->id === $target->id) {
            return $auth->hasPermission('view_own_profile');
        }

        if ($auth->canManage($target)) {
            return $auth->hasPermission('view_team_profiles') || $auth->hasPermission('view_all_employees');
        }

        return $auth->hasPermission('view_all_employees');
    }

    /**
     * Determine if a user can edit another employee's profile.
     */
    public function canEditEmployee(User $auth, User $target): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if ($auth->id === $target->id) {
            return true; // employees can always view & edit their own profile
        }

        if ($auth->canManage($target)) {
            return $auth->hasPermission('edit_direct_reports') || $auth->hasPermission('edit_employee');
        }

        return $auth->hasPermission('edit_employee');
    }

    /**
     * Determine if a user can approve a time off request for a requester.
     */
    public function canApproveTimeOff(User $auth, User $requester): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if ($auth->canManage($requester)) {
            return $auth->hasPermission('approve_time_off');
        }

        return $auth->hasPermission('approve_time_off') && $auth->isAdmin();
    }

    /**
     * Determine if a user can view a performance review for a reviewee.
     * Review types: 'self' | 'manager' | 'signed'
     * Workable rules:
     * - managers see submitted reviews for their line
     * - employees only see shared/signed reviews
     */
    public function canViewPerformanceReview(User $auth, User $reviewee, string $reviewType): bool
    {
        if ($auth->isAdmin() || $auth->hasPermission('view_all_reviews')) {
            return true;
        }

        if ($auth->canManage($reviewee)) {
            return true;
        }

        if ($auth->id === $reviewee->id) {
            return $reviewType === 'self' || $reviewType === 'signed';
        }

        return false;
    }

    /**
     * Determine if a user can access onboarding for a specific employee.
     */
    public function canAccessOnboarding(User $auth, User $employee): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if ($auth->canManage($employee)) {
            return $auth->hasPermission('manage_onboarding') || $auth->hasPermission('view_onboarding_dashboard');
        }

        return $auth->hasPermission('manage_onboarding');
    }
}
