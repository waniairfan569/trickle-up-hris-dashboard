<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subdomain', 'status', 'plan',
        'brand_name', 'logo_url', 'primary_color', 'from_email', 'timezone', 'currency',
        'trial_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** The default tenant that owns all pre-SaaS data. */
    public static function default(): ?self
    {
        return static::where('slug', 'trickle-up')->first();
    }

    /** Brand name shown in the UI / emails (falls back to the org name). */
    public function displayName(): string
    {
        return $this->brand_name ?: $this->name;
    }

    // ---- Billing / subscription --------------------------------------------

    public function planKey(): string
    {
        $key = $this->plan ?: 'trial';

        return config("plans.plans.$key") ? $key : 'trial';
    }

    public function planConfig(): array
    {
        return config('plans.plans.' . $this->planKey(), []);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function trialExpired(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    public function trialDaysLeft(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInDays($this->trial_ends_at, false)));
    }

    /** Active = paid subscription or still within trial. */
    public function isActive(): bool
    {
        return $this->status === 'active' || $this->onTrial();
    }

    public function seatLimit(): int
    {
        return (int) ($this->planConfig()['seats'] ?? 0); // 0 = unlimited
    }

    public function seatCount(): int
    {
        return \App\Models\Employee::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('is_system', false)
            ->count();
    }

    public function withinSeatLimit(): bool
    {
        $limit = $this->seatLimit();

        return $limit === 0 || $this->seatCount() < $limit;
    }

    public function hasFeature(string $feature): bool
    {
        $features = $this->planConfig()['features'] ?? [];

        return in_array('*', $features, true) || in_array($feature, $features, true);
    }
}
