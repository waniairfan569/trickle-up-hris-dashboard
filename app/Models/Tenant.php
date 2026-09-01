<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subdomain', 'status', 'plan', 'discount_percent',
        'brand_name', 'logo_url', 'primary_color', 'from_email', 'timezone', 'currency',
        'trial_ends_at', 'canceled_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'discount_percent' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptionEvents()
    {
        return $this->hasMany(SubscriptionEvent::class)->latest();
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

    /** Memoised resolved Plan model for this tenant (request-lifetime). */
    protected ?Plan $resolvedPlan = null;
    protected bool $planResolved = false;

    public function planModel(): ?Plan
    {
        if (! $this->planResolved) {
            $this->resolvedPlan = Plan::forKey($this->plan) ?: Plan::forKey('trial');
            $this->planResolved = true;
        }

        return $this->resolvedPlan;
    }

    public function planKey(): string
    {
        $p = $this->planModel();

        return $p ? $p->key : 'trial';
    }

    /**
     * The plan as an array — sourced from the DB (dynamic plans), falling back to
     * the static config only if the plans table isn't populated yet. Keeps every
     * historical caller (seatLimit / hasFeature / MRR / views) working unchanged.
     */
    public function planConfig(): array
    {
        $p = $this->planModel();

        if ($p) {
            return $p->toConfigArray();
        }

        return config('plans.plans.' . ($this->plan ?: 'trial'), config('plans.plans.trial', []));
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

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    /** The plan's list price. */
    public function planPrice(): float
    {
        return (float) ($this->planConfig()['price'] ?? 0);
    }

    /** Price after any operator discount/comp is applied. */
    public function effectivePrice(): float
    {
        $pct = max(0, min(100, (int) ($this->discount_percent ?? 0)));

        return round($this->planPrice() * (1 - $pct / 100), 2);
    }

    /** Monthly recurring revenue this company contributes (paid + active only). */
    public function mrr(): float
    {
        return $this->status === 'active' ? $this->effectivePrice() : 0.0;
    }
}
