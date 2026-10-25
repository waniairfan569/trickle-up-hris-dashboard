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
        'change_action',
        'notify_line_manager',
        'sort_order',
    ];

    /** What happens when the employee themselves changes this field. */
    public const CHANGE_ACTIONS = [
        'none'   => 'Save it straight away',
        'notify' => 'Save it, and tell HR it changed',
        'review' => 'Hold it until HR approves',
    ];

    public function changeAction(): string
    {
        return array_key_exists((string) $this->change_action, self::CHANGE_ACTIONS)
            ? $this->change_action
            : 'none';
    }

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_system' => 'boolean',
        'is_encrypted' => 'boolean',
        'employee_can_edit' => 'boolean',
        'notify_line_manager' => 'boolean',
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
            'manager' => $viewer->isAdmin() || $viewer->managesUser($employee->id),
            default => false,
        };
    }

    public function isEditableTo(User $editor, User $employee): bool
    {
        if ($editor->isAdmin()) {
            return true;
        }

        // On their OWN profile an employee may edit their own details, except
        // the ones HR keeps control of.
        if ($editor->id === $employee->id) {
            // What the workspace actually set on the field, in the template
            // builder, under "Employee Can Edit". This used to be ignored in
            // favour of the hard-coded list below, so a field an admin had
            // locked was still editable by the person it was about — including
            // their own end date, notice period and probation dates.
            if ($this->employee_can_edit === false) {
                return false;
            }

            // Kept as a floor beneath that switch: these are org and employment
            // facts, and no setting should make them self-editable.
            $neverSelfEditable = [
                'department_id', 'manager_id', 'employee_id',
                'contract_type', 'employment_type', 'job_title',
                'employee_status', 'employment_status',
                'hire_date', 'start_date',
                'pay_frequency', 'currency',
            ];

            return ! in_array($this->key, $neverSelfEditable, true);
        }

        return false;
    }
}
