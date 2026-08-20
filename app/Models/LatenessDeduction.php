<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class LatenessDeduction extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'user_id', 'year', 'month', 'late_count', 'days_deducted', 'policy_id', 'warning_sent_at',
        'reverted_at', 'reverted_by', 'reversal_status', 'reversal_reason', 'reversal_response',
        'reversal_requested_at', 'reversal_reviewed_at', 'reversal_reviewed_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'late_count' => 'integer',
        'days_deducted' => 'decimal:1',
        'warning_sent_at' => 'datetime',
        'reverted_at' => 'datetime',
        'reversal_requested_at' => 'datetime',
        'reversal_reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }

    public function reverter()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reversal_reviewed_by');
    }
}
