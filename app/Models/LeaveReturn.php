<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * An employee's request to "return early" from an approved multi-day leave —
 * i.e. come back before the leave ends and have the unused days credited back
 * to their balance. Requires HR approval (formally: leave curtailment).
 */
class LeaveReturn extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'time_off_request_id',
        'user_id',
        'return_date',
        'days_returned',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'return_date' => 'date',
        'days_returned' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(TimeOffRequest::class, 'time_off_request_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** [label, tailwind classes] for the status badge. */
    public function statusBadge(): array
    {
        return match ($this->status) {
            'approved' => ['Approved', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'],
            'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400'],
            default => ['Pending', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'],
        };
    }
}
