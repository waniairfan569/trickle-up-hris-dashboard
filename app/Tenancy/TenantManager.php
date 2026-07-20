<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Holds the current tenant for the request. Registered as a singleton, so any
 * code (models, scopes, services) can read the active tenant. When no tenant is
 * set (login page, console, seeding) scoping is a no-op — nothing breaks.
 */
class TenantManager
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /** Run a callback with tenant scoping temporarily disabled. */
    public function withoutScope(callable $callback)
    {
        $previous = $this->tenant;
        $this->tenant = null;
        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
