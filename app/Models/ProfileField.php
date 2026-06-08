<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileField extends Model
{
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
        return match ($this->visibility) {
            'public' => true,
            'internal' => $viewer->isAdmin(),
            'private' => $viewer->id === $employee->id || $viewer->isAdmin(),
            'manager' => $viewer->id === $employee->id || $viewer->id === $employee->manager_id || $viewer->isAdmin(),
            default => false,
        };
    }

    public function isEditableTo(User $editor, User $employee): bool
    {
        if ($this->is_system) {
            return $editor->isAdmin();
        }

        if ($editor->isAdmin()) {
            return true;
        }

        if ($editor->id === $employee->id && $this->employee_can_edit) {
            return true;
        }

        return false;
    }
}
