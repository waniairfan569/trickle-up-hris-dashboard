<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeOffPolicy extends Model
{
    use BelongsToTenant;
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
        'auto_assign_to_new_employees',
        'show_on_dashboard',
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
        'auto_assign_to_new_employees' => 'boolean',
        'show_on_dashboard' => 'boolean',
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

    /** Active policies flagged to auto-assign to every new employee. */
    public function scopeAutoAssignable($query)
    {
        return $query->where('is_active', true)->where('auto_assign_to_new_employees', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Work-From-Home policies. WFH is NOT time off — the employee is working,
     * just not from the office — so it must never read as leave or "out of
     * office" anywhere. There's no WFH policy type (the enum has none), so it's
     * matched by name; this scope and isWorkFromHome() are the ONLY places that
     * rule lives, so every surface agrees on what counts as WFH.
     */
    public function scopeWorkFromHome($query)
    {
        return $query->where(fn ($q) => $q
            ->whereRaw('LOWER(name) like ?', ['%work from home%'])
            ->orWhereRaw('LOWER(name) like ?', ['%wfh%']));
    }

    /** True when this policy is a Work-From-Home policy (see scopeWorkFromHome). */
    public function isWorkFromHome(): bool
    {
        $name = strtolower((string) $this->name);

        return str_contains($name, 'work from home') || str_contains($name, 'wfh');
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

    /** Minimum completed months of service before parental leave is granted. */
    public const PARENTAL_MIN_SERVICE_MONTHS = 12;

    /**
     * Whether this policy's leave applies to the given employee. Maternity leave
     * is only for married women, Paternity only for married men — and both require
     * at least 1 year (PARENTAL_MIN_SERVICE_MONTHS) of completed service. Every
     * other policy applies to everyone.
     */
    public function appliesTo(User $employee): bool
    {
        $name = strtolower((string) $this->name);
        $isMaternity = str_contains($name, 'maternity');
        $isPaternity = str_contains($name, 'paternity');

        if (!$isMaternity && !$isPaternity) {
            return true;
        }

        $gf = fn ($k) => method_exists($employee, 'getFieldValue') ? $employee->getFieldValue($k) : null;
        $married = strtolower(trim((string) $gf('marital_status'))) === 'married';
        $gender = strtolower(trim((string) $gf('gender')));

        $genderOk = $isMaternity ? ($married && $gender === 'female') : ($married && $gender === 'male');
        if (!$genderOk) {
            return false;
        }

        // Must have completed the minimum service (a brand-new hire is not yet entitled).
        $months = method_exists($employee, 'monthsOfService') ? $employee->monthsOfService() : null;

        return $months !== null && $months >= self::PARENTAL_MIN_SERVICE_MONTHS;
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
