<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Add to any tenant-owned model. It auto-scopes every query to the current
 * tenant and stamps new records with the current tenant_id.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $id = app(TenantManager::class)->id();
                if ($id) {
                    $model->tenant_id = $id;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
