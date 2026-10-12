<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable HR document template (Lateness Review, Return to Work, …).
 *
 * The `schema` is an ordered array of sections; each section has a `title` and
 * a list of `fields`. A field is:
 *   ['id' => 'unique_key', 'label' => '…', 'type' => 'text|textarea|date|
 *     checkbox|radio|select|table|signature|note', 'width' => 'full|half',
 *     'options' => [...], 'columns' => [...], 'prefill' => 'lateness|absence',
 *     'text' => '…' (for note)]
 */
class HrDocumentTemplate extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'name', 'subtitle', 'description', 'icon', 'prefill', 'rollup', 'letterhead_id',
        'schema', 'is_active', 'is_system', 'sort_order', 'created_by',
    ];

    /** One document a month listing every event, rather than one per event. */
    public function isMonthly(): bool
    {
        return $this->rollup === 'monthly';
    }

    protected $casts = [
        'schema'    => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function documents()
    {
        return $this->hasMany(HrDocument::class);
    }

    public function letterhead()
    {
        return $this->belongsTo(LetterheadTemplate::class, 'letterhead_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
