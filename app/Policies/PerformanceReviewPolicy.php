<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PerformanceReview;

class PerformanceReviewPolicy
{
    public function view(User $auth, PerformanceReview $review): bool
    {
        return $review->canBeViewedBy($auth);
    }

    public function update(User $auth, PerformanceReview $review): bool
    {
        return $review->canBeEditedBy($auth);
    }

    public function share(User $auth, PerformanceReview $review): bool
    {
        if ($auth->isAdmin()) return true;
        if ($review->type === 'manager' && $review->reviewer_id === $auth->id) return true;

        return false;
    }

    public function reopen(User $auth, PerformanceReview $review): bool
    {
        return $auth->hasRole('super_admin');
    }
}
