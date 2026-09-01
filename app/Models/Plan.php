<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A subscription plan — platform-global (owned by the operator, NOT tenant-scoped).
 * Tenants reference a plan by its stable `key`.
 */
class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key', 'name', 'price', 'currency', 'interval', 'seats', 'features',
        'trial_days', 'blurb', 'is_public', 'is_active', 'sort_order', 'stripe_price_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'seats' => 'integer',
        'features' => 'array',
        'trial_days' => 'integer',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ---- Resolution ---------------------------------------------------------

    /** Resolve a plan by its key (any status — a tenant may sit on an archived plan). */
    public static function forKey(?string $key): ?self
    {
        if (! $key) {
            return null;
        }

        return static::where('key', $key)->first();
    }

    /** Make a unique key from a name. */
    public static function makeKey(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $key = $base;
        $i = 1;
        while (static::withTrashed()->where('key', $key)->exists()) {
            $key = $base . '-' . (++$i);
        }

        return $key;
    }

    // ---- Scopes -------------------------------------------------------------

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopePublic($q)
    {
        return $q->where('is_active', true)->where('is_public', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('price');
    }

    // ---- Helpers ------------------------------------------------------------

    public function hasFeature(string $feature): bool
    {
        $features = $this->features ?? [];

        return in_array('*', $features, true) || in_array($feature, $features, true);
    }

    public function isUnlimitedSeats(): bool
    {
        return (int) $this->seats === 0;
    }

    /** How many tenants are currently on this plan. */
    public function tenantsCount(): int
    {
        return Tenant::where('plan', $this->key)->count();
    }

    /** The array shape the rest of the app historically expected from a plan. */
    public function toConfigArray(): array
    {
        return [
            'name'         => $this->name,
            'price'        => (float) $this->price,
            'seats'        => (int) $this->seats,
            'features'     => $this->features ?? [],
            'interval'     => $this->interval,
            'currency'     => $this->currency,
            'blurb'        => $this->blurb,
            'trial_days'   => (int) $this->trial_days,
            'selectable'   => (bool) $this->is_public,
            'stripe_price' => $this->stripe_price_id,
        ];
    }
}
