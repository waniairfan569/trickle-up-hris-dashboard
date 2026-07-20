<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Resolves the current tenant's white-label branding for the UI. Falls back to
 * the platform defaults when no tenant is active (login/signup) or a field is
 * unset.
 */
class Brand
{
    public static function tenant(): ?Tenant
    {
        return app(TenantManager::class)->get();
    }

    /** Workspace name shown in the sidebar / title. */
    public static function name(): string
    {
        $t = self::tenant();

        return ($t && $t->brand_name)
            ? $t->brand_name
            : (($t && $t->name) ? $t->name : (string) config('app.name', 'Trickle Hub'));
    }

    /** Logo URL — the tenant's uploaded logo, else the platform logo. */
    public static function logo(): string
    {
        $t = self::tenant();

        return ($t && $t->logo_url) ? $t->logo_url : asset('images/logo.png');
    }

    /** Optional primary accent colour (hex), or null to use the default. */
    public static function color(): ?string
    {
        $t = self::tenant();

        return ($t && $t->primary_color) ? $t->primary_color : null;
    }
}
