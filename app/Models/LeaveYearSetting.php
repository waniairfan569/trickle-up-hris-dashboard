<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveYearSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_entity_id', 'policy_id', 'name',
        'year_start_month', 'year_start_day',
        'encashment_enabled', 'encashment_type', 'encashment_value',
        'working_days_per_month',
        'carry_forward_enabled', 'carry_forward_max_days',
        'pro_rata_enabled', 'pro_rata_cutoff_day', 'pro_rata_round_to',
        'auto_renewal_enabled', 'last_renewal_date', 'next_renewal_date',
        'is_active',
    ];

    protected $casts = [
        'year_start_month' => 'integer',
        'year_start_day' => 'integer',
        'encashment_enabled' => 'boolean',
        'encashment_value' => 'decimal:2',
        'working_days_per_month' => 'integer',
        'carry_forward_enabled' => 'boolean',
        'carry_forward_max_days' => 'decimal:1',
        'pro_rata_enabled' => 'boolean',
        'pro_rata_cutoff_day' => 'integer',
        'auto_renewal_enabled' => 'boolean',
        'last_renewal_date' => 'date',
        'next_renewal_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (LeaveYearSetting $setting) {
            // Keep next_renewal_date in sync with the configured year start.
            if (empty($setting->next_renewal_date)
                || $setting->isDirty(['year_start_month', 'year_start_day'])) {
                $setting->next_renewal_date = $setting->calculateNextRenewalDate();
            }
        });
    }

    // ---- Relationships ----------------------------------------------------

    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }

    public function encashmentRecords()
    {
        return $this->hasMany(LeaveEncashmentRecord::class, 'leave_year_setting_id');
    }

    public function renewalLogs()
    {
        return $this->hasMany(LeaveRenewalLog::class, 'leave_year_setting_id');
    }

    // ---- Encashment rule ---------------------------------------------------

    /** Max encashable days for a given (possibly pro-rata) allocation. */
    public function calculateEncashmentCap(float $allocation): float
    {
        return match ($this->encashment_type) {
            'percent_of_annual' => round($allocation * ((float) $this->encashment_value / 100), 1),
            'full_remaining' => PHP_FLOAT_MAX,
            'fixed_days' => (float) $this->encashment_value,
            default => 0.0, // 'none'
        };
    }

    /** min(remaining, cap) — you can never encash more than you actually have. */
    public function calculateDaysToEncash(float $remaining, float $allocation): float
    {
        return round(min($remaining, $this->calculateEncashmentCap($allocation)), 1);
    }

    /** Human summary of the rule, e.g. "10% of annual (3.4 days on 34)". */
    public function encashmentRuleLabel(): string
    {
        $annual = (float) optional($this->policy)->days_per_year;

        return match ($this->encashment_type) {
            'percent_of_annual' => rtrim(rtrim(number_format((float) $this->encashment_value, 2), '0'), '.')
                . '% of annual allocation'
                . ($annual ? ' (max ' . $this->calculateEncashmentCap($annual) . ' days on ' . rtrim(rtrim(number_format($annual, 1), '0'), '.') . '-day policy)' : ''),
            'full_remaining' => 'Full remaining leaves encashed',
            'fixed_days' => 'Fixed cap: max ' . rtrim(rtrim(number_format((float) $this->encashment_value, 2), '0'), '.') . ' days',
            default => 'No encashment — leaves lapse',
        };
    }

    // ---- Year window --------------------------------------------------------

    /** Next upcoming occurrence of the configured year start (strictly future or today). */
    public function calculateNextRenewalDate(): Carbon
    {
        $day = max(1, min(28, (int) $this->year_start_day ?: 1)); // clamp: every month has 28
        $candidate = Carbon::create(now()->year, (int) $this->year_start_month ?: 1, $day)->startOfDay();

        if ($candidate->lte(today())) {
            $candidate->addYear();
        }

        return $candidate;
    }

    public function isDueForRenewal(): bool
    {
        return $this->next_renewal_date && $this->next_renewal_date->lte(today());
    }

    /** Start of the CURRENT leave year (the one that closes at next renewal). */
    public function currentYearStart(): Carbon
    {
        return ($this->next_renewal_date ?: $this->calculateNextRenewalDate())->copy()->subYear();
    }

    /** e.g. "July 2025 – June 2026". */
    public function getCurrentYearLabel(): string
    {
        $start = $this->currentYearStart();
        $end = $start->copy()->addYear()->subDay();

        return $start->format('F Y') . ' – ' . $end->format('F Y');
    }
}
