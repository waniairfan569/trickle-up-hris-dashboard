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
}
