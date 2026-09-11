<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * An employee's claim for compensation leave (time off in lieu of overtime):
 * "I worked overtime on <date>, please credit me <n> day(s)". HR / super admin
 * approves — crediting the compensatory policy balance — or declines it.
 */
class CompensationClaim extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'worked_date',
        'hours_worked',
        'days_claimed',
        'days_credited',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'worked_date' => 'date',
        'hours_worked' => 'decimal:2',
        'days_claimed' => 'decimal:2',
        'days_credited' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /** Tailwind classes for the status chip. */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            'rejected' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
            'cancelled' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
            default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        };
    }
}
