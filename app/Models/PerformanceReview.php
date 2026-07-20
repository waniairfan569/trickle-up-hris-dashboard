<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'cycle_id',
        'reviewee_id',
        'reviewer_id',
        'type',
        'status',
        'content',
        'submitted_at',
        'shared_at',
        'signed_at',
        'reopened_by',
    ];

    protected $casts = [
        'content' => 'array',
        'submitted_at' => 'datetime',
        'shared_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(ReviewCycle::class, 'cycle_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * Check if the authenticated user can view this review.
     */
    public function canBeViewedBy(User $auth): bool
    {
        if ($auth->isAdmin()) {
            return true;
        }

        if ($this->type === 'self') {
            // Self-review: reviewee and their manager can view
            if ($this->reviewee_id === $auth->id) return true;
            if ($this->reviewee && $auth->canManage($this->reviewee)) return true;
        }

        if ($this->type === 'manager') {
            // Manager review
            if ($this->status === 'draft') {
                return $this->reviewer_id === $auth->id;
            }
            if ($this->status === 'submitted') {
                return $this->reviewer_id === $auth->id || $auth->canManage($this->reviewer);
            }
            // If shared or signed, reviewee can also see it
            if (in_array($this->status, ['shared', 'signed'])) {
                if ($this->reviewee_id === $auth->id) return true;
                if ($this->reviewer_id === $auth->id) return true;
                if ($auth->canManage($this->reviewee)) return true;
            }
        }

        return false;
    }

    /**
     * Check if the authenticated user can edit this review.
     */
    public function canBeEditedBy(User $auth): bool
    {
        if ($this->status !== 'draft') {
            return false;
        }

        if ($this->type === 'self') {
            return $this->reviewee_id === $auth->id;
        }

        if ($this->type === 'manager') {
            return $this->reviewer_id === $auth->id;
        }

        return false;
    }
}
