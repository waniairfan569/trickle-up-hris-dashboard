<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeOffRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'policy_id',
        'requested_by',
        'start_date',
        'end_date',
        'days_requested',
        'duration_type',
        'hours_requested',
        'start_time',
        'end_time',
        'is_half_day',
        'half_day_period',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_note',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_requested' => 'decimal:2',
        'hours_requested' => 'decimal:2',
        'is_half_day' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Boot logic to automatically calculate days requested if not explicitly provided
     * (e.g. from seeders or API where frontend calc isn't trusted)
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            // Keep is_half_day in sync with duration_type for backward compatibility.
            if ($request->duration_type === 'half_day') {
                $request->is_half_day = true;
            } elseif ($request->duration_type === 'hourly') {
                $request->is_half_day = false;
            }

            if (empty($request->days_requested)) {
                if ($request->duration_type === 'hourly') {
                    $hours = $request->hours_requested
                        ?: self::hoursBetween($request->start_time, $request->end_time);
                    $request->hours_requested = $hours;
                    $request->days_requested = round($hours / self::hoursPerDayFor($request->user_id), 2);
                } elseif ($request->is_half_day || $request->duration_type === 'half_day') {
                    $request->days_requested = 0.5;
                } else {
                    $user = User::find($request->user_id);
                    $schedule = $user->workSchedule ?? WorkSchedule::default()->first();

                    if ($schedule) {
                        $request->days_requested = $schedule->countWorkingDays(
                            Carbon::parse($request->start_date),
                            Carbon::parse($request->end_date),
                            $request->user_id
                        );
                    } else {
                        // Fallback simple calculation if no schedule
                        $request->days_requested = Carbon::parse($request->start_date)
                            ->diffInDaysFiltered(function(Carbon $date) {
                                return !$date->isWeekend();
                            }, Carbon::parse($request->end_date)) + 1; // +1 inclusive
                    }
                }
            }
        });
    }

    /** Standard working hours in a day for this user (schedule → default → 8). */
    public static function hoursPerDayFor($userId): float
    {
        $user = $userId ? User::find($userId) : null;
        $schedule = ($user ? $user->workSchedule : null) ?? WorkSchedule::default()->first();
        $hours = $schedule && $schedule->hours_per_day ? (float) $schedule->hours_per_day : 8.0;

        return $hours > 0 ? $hours : 8.0;
    }

    /** Whole hours between two "H:i(:s)" times on the same day. */
    public static function hoursBetween(?string $start, ?string $end): float
    {
        if (!$start || !$end) {
            return 0.0;
        }
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);

        // Carbon 3 diffs are signed ($a->diff($b) = b - a); order so it's positive.
        return $e->greaterThan($s) ? round($s->floatDiffInHours($e), 2) : 0.0;
    }

    /** Human-friendly duration, e.g. "3 hours", "Half day (Morning)", "2 days". */
    public function getDurationLabelAttribute(): string
    {
        $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        if ($this->duration_type === 'hourly') {
            $h = $trim($this->hours_requested);
            return $h . ' hour' . (((float) $this->hours_requested) === 1.0 ? '' : 's');
        }
        if ($this->duration_type === 'half_day' || $this->is_half_day) {
            return 'Half day' . ($this->half_day_period ? ' (' . ucfirst($this->half_day_period) . ')' : '');
        }
        $d = $trim($this->days_requested);
        return $d . ' day' . (((float) $this->days_requested) === 1.0 ? '' : 's');
    }

    /** "10:00 AM – 1:00 PM" for hourly requests, else null. */
    public function getTimeRangeAttribute(): ?string
    {
        if ($this->duration_type !== 'hourly' || !$this->start_time || !$this->end_time) {
            return null;
        }
        return Carbon::parse($this->start_time)->format('g:i A') . ' – ' . Carbon::parse($this->end_time)->format('g:i A');
    }

    // --- Relationships ---

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * People with approved leave covering today, as normalized display rows.
     * Pass $userIds to scope to a manager's team. Used by the dashboard card,
     * the Team Attendance "on leave" strip and the dedicated On Leave page.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public static function onLeaveToday($userIds = null)
    {
        $q = static::where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->with(['employee:id,first_name,last_name,avatar_url', 'policy:id,name']);

        if (is_array($userIds)) {
            $q->whereIn('user_id', $userIds);
        }

        return $q->orderBy('end_date')->get()
            ->filter(fn ($r) => $r->employee)
            ->map(function ($r) {
                $emp = $r->employee;
                $name = trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')) ?: 'Employee';

                return [
                    'name' => $name,
                    'initials' => $emp->initials,
                    'avatar' => $emp->avatar_url,
                    'until' => $r->end_date->isToday() ? 'today' : $r->end_date->format('d M'),
                    'returns' => $r->end_date->copy()->addDay()->format('d M Y'),
                    'policy' => optional($r->policy)->name,
                    'half' => $r->is_half_day ? ($r->half_day_period ? ucfirst($r->half_day_period) . ' half' : 'Half day') : null,
                ];
            })->values();
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForTeam($query, User $manager)
    {
        // Manager's direct reports
        $reportIds = User::where('manager_id', $manager->id)->pluck('id');
        return $query->whereIn('user_id', $reportIds);
    }

    // --- Accessors ---

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400',
            'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400',
            'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-400',
            'cancelled' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
            default => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
        };
    }
}
