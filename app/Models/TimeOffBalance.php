<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeOffBalance extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'policy_id',
        'year',
        'opening_balance',
        'accrued',
        'used',
        'pending',
        'carried_over',
        'adjusted',
    ];

    protected $casts = [
        'year' => 'integer',
        'opening_balance' => 'decimal:2',
        'accrued' => 'decimal:2',
        'used' => 'decimal:2',
        'pending' => 'decimal:2',
        'carried_over' => 'decimal:2',
        'adjusted' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class);
    }

    /**
     * Calculated Remaining Balance
     */
    public function getRemainingAttribute()
    {
        $granted = $this->opening_balance + $this->accrued + $this->carried_over + $this->adjusted;
        $taken = $this->used + $this->pending;
        
        return (float) ($granted - $taken);
    }
}
