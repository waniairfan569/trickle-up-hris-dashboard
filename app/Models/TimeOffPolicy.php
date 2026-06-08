<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeOffPolicy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_entity_id',
        'name',
        'description',
        'type',
        'accrual_type',
        'days_per_year',
        'max_balance',
        'carry_over',
        'carry_over_max',
        'requires_approval',
        'approval_type',
        'min_notice_days',
        'allow_half_days',
        'allow_negative_balance',
        'is_paid',
        'is_active',
    ];

    protected $casts = [
        'days_per_year' => 'decimal:1',
        'max_balance' => 'decimal:1',
        'carry_over' => 'boolean',
        'carry_over_max' => 'decimal:1',
        'requires_approval' => 'boolean',
        'min_notice_days' => 'integer',
        'allow_half_days' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    public function employees()
    {
        return $this->belongsToMany(User::class, 'policy_user', 'policy_id', 'user_id')
            ->withPivot('assigned_by', 'assigned_at', 'custom_days_per_year', 'effective_from')
            ->withTimestamps();
    }

    public function balances()
    {
        return $this->hasMany(TimeOffBalance::class, 'policy_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Methods
     */
    public function getAllowanceForUser(User $user)
    {
        // Try to find the pivot
        $employee = $this->employees()->where('user_id', $user->id)->first();
        
        if ($employee && $employee->pivot->custom_days_per_year !== null) {
            return (float) $employee->pivot->custom_days_per_year;
        }

        return (float) $this->days_per_year;
    }

    public function getRemainingBalance(User $user, int $year)
    {
        $balanceRecord = $this->balances()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        if (!$balanceRecord) {
            return 0.0;
        }

        // Remaining = (opening_balance + accrued + carried_over + adjusted) - (used + pending)
        $totalGranted = $balanceRecord->opening_balance + $balanceRecord->accrued + $balanceRecord->carried_over + $balanceRecord->adjusted;
        $totalTaken = $balanceRecord->used + $balanceRecord->pending;

        return (float) ($totalGranted - $totalTaken);
    }
}
