<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A sellable module/feature in the catalog — platform-global (operator-managed).
 * Plans reference these by their stable `key` (stored in Plan::$features).
 */
class PlanFeature extends Model
{
    protected $fillable = ['key', 'label', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('label');
    }

    /**
     * [key => label] of the active catalog for pickers/labels — falls back to the
     * static config if the table hasn't been populated yet.
     */
    public static function labels(): array
    {
        try {
            $rows = static::active()->ordered()->pluck('label', 'key')->all();
        } catch (\Throwable $e) {
            $rows = [];
        }

        return $rows ?: (array) config('plans.feature_labels', []);
    }

    public static function makeKey(string $label): string
    {
        $base = Str::slug($label) ?: 'module';
        $key = $base;
        $i = 1;
        while (static::where('key', $key)->exists()) {
            $key = $base . '-' . (++$i);
        }

        return $key;
    }

    /** How many plans currently include this module. */
    public function plansCount(): int
    {
        return Plan::get()->filter(fn ($p) => in_array($this->key, $p->features ?? [], true))->count();
    }
}
