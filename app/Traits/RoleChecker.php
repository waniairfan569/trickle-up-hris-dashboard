<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\Permission;

trait RoleChecker
{
    /**
     * Determine if the model has a specific role or list of roles.
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role)) {
                    return true;
                }
            }
            return false;
        }

        // If it's a User model, check the roles association
        if (method_exists($this, 'roles') && $this->relationLoaded('roles')) {
            return $this->roles->contains('slug', $roles);
        } elseif (method_exists($this, 'roles')) {
            return $this->roles()->where('slug', $roles)->exists();
        }

        // If it's a Role model, check its own slug
        if ($this instanceof Role) {
            return $this->slug === $roles;
        }

        return false;
    }

    /**
     * Determine if the model has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Super Admin has all permissions
        if (method_exists($this, 'hasRole') && $this->hasRole('super_admin')) {
            return true;
        }

        // If it's a User model, check roles and their associated permissions
        if (method_exists($this, 'roles')) {
            if ($this->relationLoaded('roles')) {
                foreach ($this->roles as $role) {
                    if ($role->relationLoaded('permissions')) {
                        if ($role->permissions->contains('slug', $permission)) {
                            return true;
                        }
                    } else {
                        if ($role->permissions()->where('slug', $permission)->exists()) {
                            return true;
                        }
                    }
                }
                return false;
            }

            return $this->roles()->whereHas('permissions', function ($q) use ($permission) {
                $q->where('slug', $permission);
            })->exists();
        }

        // If it's a Role model, check its own permissions association
        if (method_exists($this, 'permissions')) {
            if ($this->relationLoaded('permissions')) {
                return $this->permissions->contains('slug', $permission);
            }
            return $this->permissions()->where('slug', $permission)->exists();
        }

        // If it's a Permission model, check its own slug
        if ($this instanceof Permission) {
            return $this->slug === $permission;
        }

        return false;
    }
}
