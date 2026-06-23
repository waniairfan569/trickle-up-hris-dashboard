<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'clock_in_ip',
        'clock_out_ip',
        'clock_in_lat',
        'clock_in_lng',
        'clock_out_lat',
        'clock_out_lng',
        'total_minutes_worked',
        'overtime_minutes',
        'late_minutes',
        'early_departure_minutes',
        'notes',
        'edited_by',
        'edited_at',
        'source',
        'zkteco_punch_id',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'edited_at' => 'datetime',
        'clock_in_lat' => 'decimal:7',
        'clock_in_lng' => 'decimal:7',
        'clock_out_lat' => 'decimal:7',
        'clock_out_lng' => 'decimal:7',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Clock-in formatted in the record owner's effective timezone (e.g. "09:03 AM"). */
    public function getClockInLocalAttribute(): ?string
    {
        return $this->clock_in ? app(\App\Services\TimezoneService::class)->formatForUser($this->clock_in, $this->employee, 'h:i A') : null;
    }

    /** Clock-out formatted in the record owner's effective timezone. */
    public function getClockOutLocalAttribute(): ?string
    {
        return $this->clock_out ? app(\App\Services\TimezoneService::class)->formatForUser($this->clock_out, $this->employee, 'h:i A') : null;
    }

    public function breaks()
    {
        return $this->hasMany(BreakRecord::class);
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function rawPunch()
    {
        return $this->belongsTo(ZktecoRawPunch::class, 'zkteco_punch_id');
    }

    // Accessors
    public function getHoursWorkedAttribute()
    {
        if ($this->total_minutes_worked === null) {
            return null;
        }

        $hours = floor($this->total_minutes_worked / 60);
        $minutes = $this->total_minutes_worked % 60;
        
        return "{$hours}h {$minutes}m";
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'present' => 'bg-green-100 text-green-700',
            'late' => 'bg-amber-100 text-amber-700',
            'absent' => 'bg-red-100 text-red-700',
            'on_leave' => 'bg-blue-100 text-blue-700',
            'overtime' => 'bg-purple-100 text-purple-700',
            'missing_clock_out' => 'bg-orange-100 text-orange-700',
            'weekend', 'public_holiday' => 'bg-gray-100 text-gray-500',
            'early_departure' => 'bg-yellow-100 text-yellow-700',
            'correction_pending' => 'bg-teal-100 text-teal-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    // Scopes
    public function scopeForDate(Builder $query, Carbon $date)
    {
        return $query->whereDate('date', $date->toDateString());
    }

    public function scopeForUser(Builder $query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopePresent(Builder $query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent(Builder $query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate(Builder $query)
    {
        return $query->where('status', 'late');
    }

    public function scopeForTeam(Builder $query, User $manager)
    {
        // Manager's direct reports
        $directReportIds = $manager->directReports()->pluck('id');
        return $query->whereIn('user_id', $directReportIds);
    }
}
