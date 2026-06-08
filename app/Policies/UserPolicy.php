<?php

namespace App\Policies;

use App\Models\User;
use App\Services\HRPermissionService;

class UserPolicy
{
    protected $hrPermissionService;

    public function __construct(HRPermissionService $hrPermissionService)
    {
        $this->hrPermissionService = $hrPermissionService;
    }

    public function viewAny(User $auth): bool
    {
        return true; // Filtering is handled in the controller scope
    }

    public function view(User $auth, User $target): bool
    {
        return $this->hrPermissionService->canViewEmployee($auth, $target);
    }

    public function create(User $auth): bool
    {
        return $auth->hasPermission('create_employee');
    }

    public function update(User $auth, User $target): bool
    {
        return $this->hrPermissionService->canEditEmployee($auth, $target);
    }

    public function delete(User $auth, User $target): bool
    {
        return $auth->hasRole('super_admin');
    }

    public function offboard(User $auth, User $target): bool
    {
        return $auth->hasRole('hr_admin') || $auth->hasRole('super_admin');
    }

    public function viewSalary(User $auth, User $target): bool
    {
        return $auth->hasRole('hr_admin') || $auth->hasRole('super_admin');
    }
}
