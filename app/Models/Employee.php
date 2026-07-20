<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use BelongsToTenant;
    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /** Real employees only — excludes system/owner accounts (e.g. company super-admin). */
    public function scopeReal($query)
    {
        return $query->where('is_system', false);
    }

    /** Alias of real(). */
    public function scopeNotSystem($query)
    {
        return $query->where('is_system', false);
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function manager() { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function timeOffRequests() { return $this->hasMany(TimeOffRequest::class); }
    public function subordinates() { return $this->hasMany(Employee::class, 'manager_id'); }
    public function leaveBalances() { return $this->hasMany(LeaveBalance::class); }
    public function attendanceRecords() { return $this->hasMany(AttendanceRecord::class); }
    // workSchedule relationship left as placeholder - add work_schedule_id column if needed

    public function getFullNameAttribute() {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
    public function getInitialsAttribute() {
        return strtoupper(substr($this->first_name ?? '', 0, 1) . substr($this->last_name ?? '', 0, 1));
    }
}
