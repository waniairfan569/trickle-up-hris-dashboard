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
    /** The parent groups a module can sit under, in the order they are shown. */
    public const GROUPS = [
        'Time & attendance',
        'Leave',
        'People',
        'Documents & forms',
        'Communication',
        'Reports',
        'Company setup',
        'Security & audit',
        'Assets & tools',
        'Other',
    ];

    protected $fillable = ['key', 'label', 'group', 'description', 'functions', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'functions'  => 'array',
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

    /**
     * The catalog as group => modules, in the order of self::GROUPS so the
     * sections read the same everywhere they are shown.
     */
    public static function grouped($features = null)
    {
        $features = $features ?: static::ordered()->get();
        $order = array_flip(self::GROUPS);

        return $features
            ->groupBy(fn ($f) => $f->group ?: 'Other')
            ->sortBy(fn ($rows, $group) => $order[$group] ?? count($order));
    }

    /** How many plans currently include this module. */
    public function plansCount(): int
    {
        return Plan::get()->filter(fn ($p) => in_array($this->key, $p->features ?? [], true))->count();
    }
}
