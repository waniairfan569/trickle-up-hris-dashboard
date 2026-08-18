<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use BelongsToTenant;
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

    /** Base shift-start time (local) used for lateness when an employee has no work schedule, e.g. "09:30". */
    public static function lateCutoff(): string
    {
        return config('attendance.late_after') ?: '09:30';
    }

    /**
     * Grace-period minutes after shift start before a clock-in is counted late.
     * Read live from the Attendance Report Settings page each time (0 = late the
     * moment the shift starts; 5 = late only 5 min after) — no caching, so an
     * admin's change to the grace applies immediately to every clock-in.
     */
    public static function lateGraceMinutes(): int
    {
        try {
            return max(0, (int) (\App\Models\AttendanceReportSettings::getSettings()->late_threshold_minutes ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * The local-time cutoff after which a clock-in on this local day is "late"
     * for a given employee: their shift start (work-schedule start_time, else
     * the configured base) PLUS the grace-period minutes. A clock-in at or after
     * this instant is late.
     */
    /**
     * The effective shift START time (H:i) for an employee on a given date.
     * Priority: the shift actually ASSIGNED to them for that day (dynamic, per
     * employee) → their fixed work schedule → the company-wide default. This is
     * what makes lateness follow each person's own shift (a 12:30 employee is
     * only late after 12:30, not after the 09:30 default).
     */
    public static function effectiveStartTime(?User $employee, Carbon $date): string
    {
        if ($employee) {
            try {
                $shift = app(\App\Services\ShiftService::class)->getShiftForUserOnDate($employee, $date->copy());
                if ($shift && $shift->start_time) {
                    return Carbon::parse($shift->start_time)->format('H:i');
                }
            } catch (\Throwable $e) {
                // fall through to work schedule / default
            }

            $sched = method_exists($employee, 'workSchedule') ? $employee->workSchedule : null;
            if ($sched && $sched->start_time) {
                return Carbon::parse($sched->start_time)->format('H:i');
            }
        }

        return self::lateCutoff();
    }

    /**
     * The expected shift END datetime for an employee on a given date (handles
     * shifts that cross midnight). Assigned shift → work schedule → null.
     * Drives overtime / early-departure so they track each person's shift.
     */
    public static function expectedEndDateTimeFor(?User $employee, Carbon $date): ?Carbon
    {
        if (!$employee) {
            return null;
        }

        try {
            $expected = app(\App\Services\ShiftService::class)->getExpectedTimesForUserOnDate($employee, $date->copy());
            if ($expected && !empty($expected['end'])) {
                return $expected['end']->copy();
            }
        } catch (\Throwable $e) {
            // fall through to work schedule
        }

        $sched = method_exists($employee, 'workSchedule') ? $employee->workSchedule : null;
        if ($sched && $sched->end_time) {
            // Parse in the employee's timezone so the instant matches the stored clock-out.
            $tz = app(\App\Services\TimezoneService::class)->getEffectiveTimezone($employee);
            return Carbon::parse($date->toDateString() . ' ' . $sched->end_time, $tz);
        }

        return null;
    }

    public static function lateCutoffFor(?User $employee, Carbon $localIn): Carbon
    {
        $base = self::effectiveStartTime($employee, $localIn);

        return Carbon::parse($localIn->toDateString() . ' ' . $base, $localIn->getTimezone())
            ->addMinutes(self::lateGraceMinutes());
    }

    /** Effective on-time cutoff (base shift start + grace) as a label, e.g. "9:35 AM". */
    public static function lateCutoffLabel(): string
    {
        return Carbon::parse(self::lateCutoff())->addMinutes(self::lateGraceMinutes())->format('g:i A');
    }

    /**
     * Approved partial-day leave (half-day or hourly) covering the given date.
     * Returns 'half_day' | 'hourly' | null. Someone on a half-day leave who
     * clocks in around midday is ON LEAVE, not late — so a partial-day leave
     * suppresses the "late" status (and half-day shows as its own status).
     */
    public static function partialDayLeaveFor(int $userId, string $date): ?string
    {
        $request = \App\Models\TimeOffRequest::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($q) {
                $q->where('is_half_day', true)->orWhereIn('duration_type', ['half_day', 'hourly']);
            })
            ->first();

        if (!$request) {
            return null;
        }

        return ($request->is_half_day || $request->duration_type === 'half_day') ? 'half_day' : 'hourly';
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
     * Recompute status + minutes from the current clock_in / clock_out. This is
     * the SINGLE source of truth for deriving a record's status, so it must stay
     * in step with AttendanceService::clockOut():
     *   - late      — clock-in (in the employee's own timezone) after the shift
     *                 start + grace cutoff;
     *   - overtime / early_departure — clock-out vs the employee's ASSIGNED SHIFT
     *                 end for that date (shift-aware, falls back to work schedule),
     *                 not a generic company schedule;
     *   - present / absent otherwise.
     * Everything is recomputed from scratch (minutes reset first) so correcting a
     * clock-out clears a stale early-departure/overtime. Leave / holiday / weekend
     * records are left untouched. Used on clock-out AND after any admin edit.
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
            $this->overtime_minutes = 0;
            $this->early_departure_minutes = 0;
            return;
        }

        // Worked minutes (minus completed breaks) when both ends are present.
        if ($this->clock_out) {
            $breakMinutes = $this->breaks()->whereNotNull('break_end')->sum('duration_minutes') ?? 0;
            // Carbon 3 diffs are signed; use earlier->diff(later) so the span is positive.
            $this->total_minutes_worked = max(0, (int) round($this->clock_in->diffInMinutes($this->clock_out)) - $breakMinutes);
        }

        // Late? Compare the clock-in (in the employee's timezone) to the cutoff
        // (shift start + grace period).
        $tz = app(\App\Services\TimezoneService::class);
        $localIn = $tz->toUserTime($this->clock_in, $this->employee);
        $cutoff = self::lateCutoffFor($this->employee, $localIn);

        if ($localIn->greaterThanOrEqualTo($cutoff)) {
            $this->late_minutes = (int) max(1, round($cutoff->diffInMinutes($localIn)));
            $this->status = 'late';
        } else {
            $this->late_minutes = 0;
            $this->status = 'present';
        }

        // Overtime / early-departure vs the employee's ASSIGNED SHIFT end for this
        // date (shift-aware — the same source clock-out uses). Recomputed from
        // zero so a corrected time clears any stale value. The MINUTES are always
        // recorded, but a late arrival keeps its "late" status — lateness is never
        // hidden by overtime / early-departure (those only relabel an on-time day).
        $this->overtime_minutes = 0;
        $this->early_departure_minutes = 0;
        if ($this->clock_out) {
            $settings = \App\Models\AttendanceSetting::first() ?? new \App\Models\AttendanceSetting();
            $expectedEnd = self::expectedEndDateTimeFor($this->employee, Carbon::parse($this->date));
            if ($expectedEnd) {
                $overtimeStart = $expectedEnd->copy()->addMinutes((int) $settings->overtime_threshold_minutes);
                if ($this->clock_out->greaterThan($overtimeStart)) {
                    $this->overtime_minutes = (int) $expectedEnd->diffInMinutes($this->clock_out);
                    if ($this->status !== 'late') {
                        $this->status = 'overtime';
                    }
                } else {
                    $earlyDeparture = $expectedEnd->copy()->subMinutes((int) $settings->early_departure_threshold_minutes);
                    if ($this->clock_out->lessThan($earlyDeparture)) {
                        $this->early_departure_minutes = (int) $this->clock_out->diffInMinutes($expectedEnd);
                        if ($this->status !== 'late') {
                            $this->status = 'early_departure';
                        }
                    }
                }
            }
        }

        // Approved partial-day leave overrides late/early-departure: a half-day
        // shows as "half_day", an hourly leave just isn't late.
        $partial = self::partialDayLeaveFor((int) $this->user_id, Carbon::parse($this->date)->toDateString());
        if ($partial === 'half_day') {
            $this->status = 'half_day';
            $this->late_minutes = 0;
        } elseif ($partial === 'hourly' && $this->status === 'late') {
            $this->status = 'present';
            $this->late_minutes = 0;
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
            'half_day' => 'bg-indigo-100 text-indigo-700',
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
        // Manager's team: direct reports (primary) + additional-managed.
        return $query->whereIn('user_id', $manager->teamMemberIds()->all());
    }
}
