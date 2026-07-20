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
            if (!empty($model->tenant_id)) {
                return;
            }

            // 1) The active request tenant, if any.
            $id = app(TenantManager::class)->id();

            // 2) Background context (console jobs, punches): derive the tenant
            //    from the record's owning user so it's never left unscoped.
            if (!$id && !empty($model->getAttribute('user_id'))) {
                $id = \App\Models\User::withoutGlobalScopes()
                    ->whereKey($model->getAttribute('user_id'))
                    ->value('tenant_id');
            }

            // 3) Single-tenant deployments: fall back to the only tenant. Stops
            //    applying automatically once a second tenant is created.
            if (!$id) {
                $only = \App\Models\Tenant::query()->limit(2)->pluck('id');
                if ($only->count() === 1) {
                    $id = $only->first();
                }
            }

            if ($id) {
                $model->tenant_id = $id;
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
