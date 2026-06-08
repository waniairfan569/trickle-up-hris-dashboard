<?php

namespace App\Events;

use App\Models\PerformanceReview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewShared
{
    use Dispatchable, SerializesModels;

    public $review;

    public function __construct(PerformanceReview $review)
    {
        $this->review = $review;
    }
}
