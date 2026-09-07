<?php

namespace App\Models;

use App\Traits\RoleChecker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes, RoleChecker;

    // RBAC System constants
    const SUPER_ADMIN = 'super_admin';
    const HR_ADMIN = 'hr_admin';
    const MANAGER = 'manager';
    const EMPLOYEE = 'employee';
    const RESTRICTED = 'restricted';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    /**
     * Relationship with permissions (many-to-many).
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Relationship with users (many-to-many).
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /** Grantable feature keys attached to this role (role_feature table). */
    public function featureKeys(): array
    {
        return \Illuminate\Support\Facades\DB::table('role_feature')
            ->where('role_id', $this->id)->pluck('feature_key')->all();
    }

    /** Replace this role's feature set with the given (sanitized) keys. */
    public function syncFeatures(array $keys): void
    {
        $keys = \App\Support\FeatureCatalog::sanitize($keys);
        $now = now();
        $rows = array_map(fn ($k) => [
            'role_id' => $this->id, 'feature_key' => $k,
            'created_at' => $now, 'updated_at' => $now,
        ], $keys);

        \Illuminate\Support\Facades\DB::transaction(function () use ($rows) {
            \Illuminate\Support\Facades\DB::table('role_feature')->where('role_id', $this->id)->delete();
            if ($rows) {
                \Illuminate\Support\Facades\DB::table('role_feature')->insert($rows);
            }
        });
    }

    /** True for the built-in roles that can't be deleted/renamed. */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
