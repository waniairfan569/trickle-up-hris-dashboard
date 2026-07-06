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

    /** Time of day (local) after which a clock-in counts as late, e.g. "09:30". */
    public static function lateCutoff(): string
    {
        return config('attendance.late_after') ?: '09:30';
    }

    /**
     * Find the (user, date) record — INCLUDING a soft-deleted one — restoring it
     * if trashed, or return a fresh unsaved model. The attendance_records table
     * has a UNIQUE(user_id, date) index that still counts soft-deleted rows, so
     * a plain firstOrCreate would try to INSERT over a trashed row and blow up
     * with a 1062 duplicate-entry error. Always route (user, date) upserts here.
     */
    public static function findOrNewForDate($userId, $date): self
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : (string) $date;

        $record = static::withTrashed()
            ->where('user_id', $userId)
            ->whereDate('date', $dateStr)
            ->first();

        if ($record) {
            if ($record->trashed()) {
                $record->restore();
            }
            return $record;
        }

        return new static(['user_id' => $userId, 'date' => $dateStr]);
    }

    /**
     * Recompute status, late_minutes and total_minutes_worked from the current
     * clock_in / clock_out (used after an admin edits the times, or on clock-out).
     * An employee whose clock-in — in their own timezone — is after the late
     * cutoff is marked "late". Leave / holiday / weekend records are left alone.
     */
    public function recalculate(): void
    {
        if (in_array($this->status, ['on_leave', 'public_holiday', 'weekend'], true)) {
            return;
        }

        // No clock-in at all -> absent.
        if (!$this->clock_in) {
            $this->status = 'absent';
            $this->late_minutes = 0;
            $this->total_minutes_worked = 0;
            return;
        }

        // Worked minutes (minus completed breaks) when both ends are present.
        if ($this->clock_out) {
            $breakMinutes = $this->breaks()->whereNotNull('break_end')->sum('duration_minutes') ?? 0;
            // Carbon 3 diffs are signed; use earlier->diff(later) so the span is positive.
            $this->total_minutes_worked = max(0, (int) round($this->clock_in->diffInMinutes($this->clock_out)) - $breakMinutes);
        }

        // Late? Compare the clock-in (in the employee's timezone) to the cutoff.
        $tz = app(\App\Services\TimezoneService::class);
        $localIn = $tz->toUserTime($this->clock_in, $this->employee);
        $cutoff = Carbon::parse($localIn->toDateString() . ' ' . self::lateCutoff(), $localIn->getTimezone());

        if ($localIn->greaterThanOrEqualTo($cutoff)) {
            $this->late_minutes = (int) max(1, round($cutoff->diffInMinutes($localIn)));
            $this->status = 'late';
        } else {
            $this->late_minutes = 0;
            // On time: clear a previous "late"/"absent" but keep overtime/early-departure.
            if (in_array($this->status, ['absent', 'late', 'missing_clock_out'], true)) {
                $this->status = 'present';
            }
        }
    }

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

    /**
     * The day's clock-in / clock-out sessions in order. Re-clocking in after a
     * clock-out stores the out-period as an 'other' break, so each such gap
     * splits the day into separate sessions. Sub-1-minute sessions (rapid
     * re-clicks) are filtered out as noise.
     *
     * @return array<int, array{in: ?string, out: ?string}>
     */
    public function sessionSequence(): array
    {
        if (!$this->clock_in) {
            return [];
        }

        $tz = app(\App\Services\TimezoneService::class);
        $user = $this->employee;

        $gaps = $this->breaks()
            ->where('break_type', 'other')
            ->whereNotNull('break_end')
            ->orderBy('break_start')
            ->get();

        $pairs = [];
        $currentIn = $this->clock_in;
        foreach ($gaps as $gap) {
            $pairs[] = [$currentIn, $gap->break_start];
            $currentIn = $gap->break_end;
        }
        if ($currentIn) {
            $pairs[] = [$currentIn, $this->clock_out];
        }

        $sessions = [];
        foreach ($pairs as [$in, $out]) {
            // Skip completed sessions shorter than a minute (rapid re-clicks).
            if ($in && $out && $in->diffInSeconds($out) < 60) {
                continue;
            }
            $sessions[] = [
                'in' => $in ? $tz->formatForUser($in, $user, 'h:i A') : null,
                'out' => $out ? $tz->formatForUser($out, $user, 'h:i A') : null,
            ];
        }

        return $sessions;
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
