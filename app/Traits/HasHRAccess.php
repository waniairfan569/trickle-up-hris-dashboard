<?php

namespace App\Traits;

use App\Models\User;
use App\Services\HRPermissionService;
use Illuminate\Support\Facades\App;

trait HasHRAccess
{
    /**
     * Cache for permission checks dynamically computed in this request.
     *
     * @var array
     */
    protected array $hrAccessCache = [];

    /**
     * Determine if this user can view the target user's employee profile.
     */
    public function canView(User $target): bool
    {
        $cacheKey = "view_{$target->id}";
        if (!array_key_exists($cacheKey, $this->hrAccessCache)) {
            $this->hrAccessCache[$cacheKey] = App::make(HRPermissionService::class)->canViewEmployee($this, $target);
        }
        return $this->hrAccessCache[$cacheKey];
    }

    /**
     * Determine if this user can edit the target user's employee profile.
     */
    public function canEdit(User $target): bool
    {
        $cacheKey = "edit_{$target->id}";
        if (!array_key_exists($cacheKey, $this->hrAccessCache)) {
            $this->hrAccessCache[$cacheKey] = App::make(HRPermissionService::class)->canEditEmployee($this, $target);
        }
        return $this->hrAccessCache[$cacheKey];
    }

    /**
     * Determine if this user can approve a time off request for a requester.
     */
    public function canApproveTimeOffFor(User $requester): bool
    {
        $cacheKey = "approve_time_off_{$requester->id}";
        if (!array_key_exists($cacheKey, $this->hrAccessCache)) {
            $this->hrAccessCache[$cacheKey] = App::make(HRPermissionService::class)->canApproveTimeOff($this, $requester);
        }
        return $this->hrAccessCache[$cacheKey];
    }

    /**
     * Determine if this user can view a performance review of a specific type for a reviewee.
     */
    public function canViewPerformance(User $reviewee, string $reviewType): bool
    {
        $cacheKey = "view_performance_{$reviewee->id}_{$reviewType}";
        if (!array_key_exists($cacheKey, $this->hrAccessCache)) {
            $this->hrAccessCache[$cacheKey] = App::make(HRPermissionService::class)->canViewPerformanceReview($this, $reviewee, $reviewType);
        }
        return $this->hrAccessCache[$cacheKey];
    }

    /**
     * Determine if this user can access onboarding dashboard/details for an employee.
     */
    public function canAccessOnboardingFor(User $employee): bool
    {
        $cacheKey = "onboarding_{$employee->id}";
        if (!array_key_exists($cacheKey, $this->hrAccessCache)) {
            $this->hrAccessCache[$cacheKey] = App::make(HRPermissionService::class)->canAccessOnboarding($this, $employee);
        }
        return $this->hrAccessCache[$cacheKey];
    }
}
