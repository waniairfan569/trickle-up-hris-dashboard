<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeOffRequest extends Model
{
    use BelongsToTenant;
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
        'return_note',
        'return_note_at',
        'return_note_ip',
        'status',
        'approval_stage',
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
        'return_note_at' => 'datetime',
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
                    $request->days_requested = self::daysForHours($hours, $request->user_id, $request->policy_id);
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

    /**
     * Day-equivalent for an hourly leave, as this policy charges it.
     *
     * "band" is the rule this started with, written around one company's
     * 8.5-hour day: two hours up to half a day costs half a day, and anything
     * longer is charged for the time taken, capped at one day. It has a cliff
     * at two hours — one hour fifty-nine costs 0.23 of a day and two hours
     * costs 0.5 — which is why a policy can now choose not to have it.
     *
     * "exact" charges the time actually taken, against the length of that
     * employee's working day, with no bands and no cliff.
     *
     * The policy is optional so older callers keep working; without one, the
     * band applies, which is what every policy did before this was a choice.
     */
    public static function daysForHours(float $hours, $userId, $policy = null): float
    {
        if ($hours <= 0) {
            return 0.0;
        }

        $hpd = self::hoursPerDayFor($userId);   // e.g. 8.5

        if (! $policy instanceof TimeOffPolicy) {
            $policy = $policy ? TimeOffPolicy::find($policy) : null;
        }

        $exact = $policy && $policy->hourlyChargeMode() === 'exact';

        if (! $exact) {
            $half = $hpd / 2;                    // e.g. 4.25
            if ($hours >= 2 && $hours <= $half) {
                return 0.5;
            }
        }

        return min(1.0, round($hours / $hpd, 2));
    }

    /** Standard working hours in a day (schedule hours → derived from times → 8.5). */
    public static function hoursPerDayFor($userId): float
    {
        $user = $userId ? User::find($userId) : null;
        $schedule = ($user ? $user->workSchedule : null) ?? WorkSchedule::default()->first();

        if ($schedule) {
            if ($schedule->hours_per_day && (float) $schedule->hours_per_day > 0) {
                return (float) $schedule->hours_per_day;
            }
            if ($schedule->start_time && $schedule->end_time) {
                $derived = self::hoursBetween($schedule->start_time, $schedule->end_time);
                if ($derived > 0) {
                    return $derived;
                }
            }
        }

        return 8.5; // company standard workday (42.5h/week over 5 days)
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
            // Show a readable "3 hours" / "1h 30m" / "45 min" instead of an
            // obscure fraction-of-a-day decimal.
            $mins = (int) round((float) $this->hours_requested * 60);
            if ($mins <= 0) {
                return '0 min';
            }
            $h = intdiv($mins, 60);
            $m = $mins % 60;
            if ($h && $m) {
                return $h . 'h ' . $m . 'm';
            }
            if ($h) {
                return $h . ' hour' . ($h === 1 ? '' : 's');
            }
            return $m . ' min';
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

    /**
     * Leaves that should trigger a Return-to-Work document: unplanned / casual /
     * sick / emergency, or work-from-home — NOT planned/annual/Eid leave.
     */
    public function scopeReturnToWorkEligible($query)
    {
        return $query->whereHas('policy', function ($p) {
            $p->where(function ($pp) {
                foreach (['unplanned', 'casual', 'sick', 'emergency', 'wfh', 'work from home'] as $kw) {
                    $pp->orWhereRaw('LOWER(name) LIKE ?', ['%' . $kw . '%']);
                }
            });
        });
    }

    /**
     * Leaves this employee is back from and has not yet written a return note
     * for. The portal asks for these the same way it asks why someone was late,
     * so a return to work is recorded without anyone having to sit down.
     *
     * The window is the admin's "Ask for reasons from" date, shared with the
     * lateness prompt — one setting decides how far back the portal asks.
     */
    public static function outstandingReturnsFor(User $employee, int $withinDays = 45)
    {
        $since = AttendanceReportSettings::getSettings()->lateness_prompt_from ?? null;
        $from = $since ? Carbon::parse($since)->startOfDay() : Carbon::today()->subDays($withinDays);

        $leaves = static::where('user_id', $employee->id)
            ->where('status', 'approved')
            ->returnToWorkEligible()
            ->whereNull('return_note')
            ->whereDate('end_date', '<', Carbon::today()->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->with('policy')
            ->orderByDesc('end_date')
            ->get();

        if ($leaves->isEmpty()) {
            return $leaves;
        }

        // A leave already written up in a document that has gone out has been
        // dealt with. A draft has not gone anywhere and is rebuilt when it is
        // regenerated, so it must not stop the employee answering.
        $documented = HrDocument::where('user_id', $employee->id)
            ->where('status', '!=', 'draft')
            ->whereHas('template', fn ($q) => $q->whereIn('prefill', ['absence', 'hourly', 'wfh']))
            ->get(['period_start', 'period_end']);

        if ($documented->isEmpty()) {
            return $leaves;
        }

        return $leaves->reject(fn ($lv) => $documented->contains(
            fn ($doc) => $doc->period_start && $doc->period_end
                && Carbon::parse($lv->start_date)->betweenIncluded($doc->period_start, $doc->period_end)
        ))->values();
    }

    /** "12 Oct 2026", or "12 – 15 Oct 2026" — how a leave reads in a list. */
    public function getDateLabelAttribute(): string
    {
        $from = Carbon::parse($this->start_date);
        $to = Carbon::parse($this->end_date);

        return $from->isSameDay($to)
            ? $from->format('d M Y')
            : $from->format('d M') . ' – ' . $to->format('d M Y');
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
            ->excludingWorkFromHome()
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

    /**
     * Real time off only — Work From Home is dropped. Someone on WFH is working
     * (remotely), so they are neither "on leave" nor "out of office": they still
     * belong on the attendance sheet and are still expected to clock in.
     * Requests with no policy at all are kept (they can't be identified as WFH).
     */
    public function scopeExcludingWorkFromHome($query)
    {
        return $query->whereDoesntHave('policy', fn ($q) => $q->workFromHome());
    }

    /** Only Work-From-Home requests — the inverse of excludingWorkFromHome(). */
    public function scopeWorkFromHomeOnly($query)
    {
        return $query->whereHas('policy', fn ($q) => $q->workFromHome());
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForTeam($query, User $manager)
    {
        // Manager's team: direct reports (primary) + additional-managed.
        return $query->whereIn('user_id', $manager->teamMemberIds()->all());
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
