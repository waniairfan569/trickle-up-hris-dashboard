<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that limits every query to the current tenant. If no tenant is
 * active for the request (e.g. before login, in the console, during seeding),
 * the scope does nothing — so single-tenant/boot flows are unaffected.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenants = app(TenantManager::class);
        if ($tenants->check()) {
            $builder->where($model->getTable() . '.tenant_id', $tenants->id());
        }
    }
}
