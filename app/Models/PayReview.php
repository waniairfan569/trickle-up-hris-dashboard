<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'effective_date',
        'previous_salary',
        'new_salary',
        'increment_amount',
        'increment_percent',
        'months_since_last',
        'currency',
        'pay_type',
        'pay_schedule',
        'review_type',
        'reason',
        'approved_by',
        'note',
        'created_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'previous_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
        'increment_amount' => 'decimal:2',
        'increment_percent' => 'decimal:2',
        'months_since_last' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getReviewTypeLabelAttribute(): string
    {
        return match ($this->review_type) {
            'annual' => 'Annual',
            'mid_year' => 'Mid-Year',
            'promotion' => 'Promotion',
            'correction' => 'Correction',
            'market_adjustment' => 'Market Adjustment',
            'joining' => 'Joining',
            default => ucfirst((string) $this->review_type),
        };
    }

    /**
     * Compute Active / Upcoming / Historical status for a collection of an
     * employee's reviews. Only the latest already-effective record is Active;
     * future-dated ones are Upcoming; the rest are Historical.
     */
    public static function withStatuses($reviews)
    {
        $today = now()->startOfDay();
        $activeId = $reviews
            ->filter(fn ($r) => $r->effective_date->lte($today))
            ->sortByDesc('effective_date')
            ->first()?->id;

        return $reviews->sortByDesc('effective_date')->map(function ($r) use ($today, $activeId) {
            $r->status = $r->effective_date->gt($today)
                ? 'upcoming'
                : ($r->id === $activeId ? 'active' : 'historical');

            return $r;
        })->values();
    }
}
