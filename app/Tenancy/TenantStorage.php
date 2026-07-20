<?php

namespace App\Tenancy;

/**
 * Namespaces stored files under the current tenant so one agency's uploads
 * live in their own folder (tenants/{id}/...). Existing records keep their
 * stored paths, so this only affects NEW uploads — fully backward compatible.
 */
class TenantStorage
{
    public static function path(string $base): string
    {
        $base = ltrim($base, '/');
        $id = app(TenantManager::class)->id();

        return $id ? "tenants/{$id}/{$base}" : $base;
    }
}
