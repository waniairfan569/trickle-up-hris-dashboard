<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'date',
        'end_date',
        'location',
        'color',
        'status',
        'created_by',
        'is_published',
        'published_at',
        'published_by',
        'visibility',
        'is_pinned',
        'notify_employees',
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'is_published' => 'boolean',
        'is_pinned' => 'boolean',
        'notify_employees' => 'boolean',
        'published_at' => 'datetime',
    ];

    // --- Relationships -------------------------------------------------------

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function audiences()
    {
        return $this->hasMany(EventAudience::class);
    }

    // --- Scopes --------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Only events visible to employees. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Events this specific user is allowed to see: published, AND either shown to
     * everyone, or to the user's department, or to the user individually.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) use ($user) {
                $q->where('visibility', 'all')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'department')
                            ->whereHas('audiences', fn ($a) => $a
                                ->where('audience_type', 'department')
                                ->where('audience_id', $user->department_id));
                    })
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'specific')
                            ->whereHas('audiences', fn ($a) => $a
                                ->where('audience_type', 'user')
                                ->where('audience_id', $user->id));
                    });
            });
    }

    // --- Publishing ----------------------------------------------------------

    public function publish(User $by): void
    {
        $this->is_published = true;
        $this->published_at = now();
        $this->published_by = $by->id;
        $this->save();
    }

    public function unpublish(): void
    {
        $this->is_published = false;
        $this->published_at = null;
        $this->save();
    }

    // --- Accessors -----------------------------------------------------------

    public function getIsMultiDayAttribute(): bool
    {
        return $this->end_date && $this->end_date->gt($this->date);
    }

    /**
     * The event's colour as a hex value for FullCalendar / dots. Handles both the
     * Tailwind-style names the existing form stores (brand, indigo, …) and plain
     * colour names, and lets a raw hex pass through. Defaults to brand yellow.
     */
    public function getColorHexAttribute(): string
    {
        $key = strtolower(trim((string) $this->color));

        if (preg_match('/^#?[0-9a-f]{6}$/i', $key)) {
            return '#' . strtoupper(ltrim($key, '#'));
        }

        return [
            // Stored Tailwind-ish names used by the existing colour dropdown.
            'brand'   => '#F5C800',
            'indigo'  => '#6366F1',
            'emerald' => '#10B981',
            'rose'    => '#F43F5E',
            'sky'     => '#0EA5E9',
            // Friendly colour names + synonyms.
            'yellow'  => '#F5C800',
            'blue'    => '#3B82F6',
            'red'     => '#EF4444',
            'green'   => '#10B981',
            'purple'  => '#8B5CF6',
            'orange'  => '#F97316',
            'pink'    => '#EC4899',
            'gray'    => '#6B7280',
            'grey'    => '#6B7280',
        ][$key] ?? '#F5C800';
    }
}
