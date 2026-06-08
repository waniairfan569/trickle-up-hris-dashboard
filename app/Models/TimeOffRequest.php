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
        'days_requested' => 'decimal:1',
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
            if (empty($request->days_requested)) {
                if ($request->is_half_day) {
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

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
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
