<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LeaveEncashmentRecord extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_entity_id', 'user_id', 'policy_id', 'leave_year_setting_id',
        'leave_year_label', 'renewal_year',
        'annual_allocation', 'is_pro_rata', 'pro_rata_months',
        'days_remaining_before_renewal',
        'encashment_type', 'encashment_value', 'encashment_cap_days',
        'days_to_encash', 'daily_rate', 'monthly_salary_snapshot',
        'encashment_amount', 'days_lapsed', 'currency',
        'status', 'processed_by', 'processed_at',
        'payment_date', 'payment_reference', 'admin_notes',
    ];

    protected $casts = [
        'renewal_year' => 'integer',
        'annual_allocation' => 'decimal:1',
        'is_pro_rata' => 'boolean',
        'pro_rata_months' => 'integer',
        'days_remaining_before_renewal' => 'decimal:1',
        'encashment_value' => 'decimal:2',
        'encashment_cap_days' => 'decimal:1',
        'days_to_encash' => 'decimal:1',
        'daily_rate' => 'decimal:2',
        'monthly_salary_snapshot' => 'decimal:2',
        'encashment_amount' => 'decimal:2',
        'days_lapsed' => 'decimal:1',
        'processed_at' => 'datetime',
        'payment_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }

    public function setting()
    {
        return $this->belongsTo(LeaveYearSetting::class, 'leave_year_setting_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getFormattedAmountAttribute(): string
    {
        return $this->currency . ' ' . number_format((float) $this->encashment_amount, 2);
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            'approved' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
            'paid' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            'rejected' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
