<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileField extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'section_id',
        'name',
        'key',
        'type',
        'options',
        'placeholder',
        'is_required',
        'is_system',
        'is_encrypted',
        'visibility',
        'employee_can_edit',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_system' => 'boolean',
        'is_encrypted' => 'boolean',
        'employee_can_edit' => 'boolean',
    ];

    public function section()
    {
        return $this->belongsTo(ProfileSection::class, 'section_id');
    }

    public function values()
    {
        return $this->hasMany(EmployeeFieldValue::class, 'field_id');
    }

    public function isVisibleTo(User $viewer, User $employee): bool
    {
        // Employees can see ALL of their own fields (every visibility level) on their
        // own profile. Colleagues only see public fields.
        if ($viewer->id === $employee->id) {
            return true;
        }

        return match ($this->visibility) {
            'public' => true,
            'internal' => $viewer->isAdmin(),
            'private' => $viewer->isAdmin(),
            'manager' => $viewer->id === $employee->manager_id || $viewer->isAdmin(),
            default => false,
        };
    }

    public function isEditableTo(User $editor, User $employee): bool
    {
        if ($editor->isAdmin()) {
            return true;
        }

        // On their OWN profile an employee may edit their own details — including
        // full name, work email and personal email — except org/HR-controlled
        // fields which only super admin / HR admin can change.
        if ($editor->id === $employee->id) {
            $adminOnly = [
                'department_id', 'manager_id', 'employee_id',
                // Employment & compensation — HR-controlled, not self-editable.
                'contract_type', 'employment_type', 'job_title',
                'employee_status', 'employment_status',
                'hire_date', 'start_date',
                'pay_frequency', 'currency',
            ];
            return !in_array($this->key, $adminOnly, true);
        }

        return false;
    }
}
