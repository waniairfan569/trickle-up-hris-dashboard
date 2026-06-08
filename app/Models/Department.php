<?php
namespace App\Models;

use App\Traits\RoleChecker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use RoleChecker, SoftDeletes;

    protected $fillable = [
        'company_id',
        'company_entity_id',
        'name',
        'slug',
        'description',
        'parent_id',
        'head_user_id',
        'color',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company entity that owns the department.
     */
    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    /**
     * Self-referencing parent department.
     */
    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }
    
    /**
     * Self-referencing sub-departments (children).
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }
    
    /**
     * Department Head
     */
    public function head()
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /**
     * Relationship with users directly mapped to this department.
     */
    public function employees()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    // Legacy relationships - keeping them so we don't break old code
    public function company() { return $this->belongsTo(Company::class); }
    public function jobs() { return $this->hasMany(Job::class); }
    public function legacyEmployees() { return $this->hasMany(Employee::class); }
    public function userAssignments() { return $this->hasMany(UserDepartmentAssignment::class); }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Count of employees including sub-departments recursively.
     */
    public function allEmployeesCount()
    {
        $count = $this->employees()->count();
        foreach ($this->children as $child) {
            $count += $child->allEmployeesCount();
        }
        return $count;
    }

    /**
     * Returns full nested name e.g. "Engineering > Frontend"
     */
    public function getFullNameAttribute()
    {
        if ($this->parent) {
            return $this->parent->full_name . ' > ' . $this->name;
        }
        return $this->name;
    }
}
