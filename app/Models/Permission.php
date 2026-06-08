<?php

namespace App\Models;

use App\Traits\RoleChecker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes, RoleChecker;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    /**
     * Relationship with roles (many-to-many).
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Local scope to filter permissions by a specific module.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }
}
