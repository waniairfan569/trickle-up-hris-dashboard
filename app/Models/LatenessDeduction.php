<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LatenessDeduction extends Model
{
    protected $fillable = [
        'user_id', 'year', 'month', 'late_count', 'days_deducted', 'policy_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'late_count' => 'integer',
        'days_deducted' => 'decimal:1',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }
}
