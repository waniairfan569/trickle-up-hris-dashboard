<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeTrackingPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'submission_method',
        'allow_timesheet_editing',
        'geofencing_enabled',
        'reminders_frequency',
        'custom_reminder_times',
    ];

    protected $casts = [
        'allow_timesheet_editing' => 'boolean',
        'geofencing_enabled' => 'boolean',
        'custom_reminder_times' => 'array',
    ];

    public function entities()
    {
        return $this->belongsToMany(CompanyEntity::class, 'time_tracking_policy_entity');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'time_tracking_policy_department');
    }

    public function sites()
    {
        return $this->belongsToMany(OfficeLocation::class, 'time_tracking_policy_site');
    }

    /** Active employees this policy applies to (entity AND department scope; empty = all). */
    public function scopedEmployeesQuery()
    {
        $entityIds = $this->entities->pluck('id')->all();
        $deptIds = $this->departments->pluck('id')->all();

        return User::where('account_status', '!=', 'deactivated')
            ->when($entityIds, fn ($q) => $q->whereIn('company_entity_id', $entityIds))
            ->when($deptIds, fn ($q) => $q->whereIn('department_id', $deptIds));
    }

    public function getEmployeeCountAttribute(): int
    {
        return $this->scopedEmployeesQuery()->count();
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->submission_method === 'manual' ? 'Submit timesheet entry' : 'Clock in / Clock out';
    }
}
