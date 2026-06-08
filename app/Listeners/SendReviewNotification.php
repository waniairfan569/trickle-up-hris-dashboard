<?php

namespace App\Listeners;

use App\Events\ReviewSubmitted;
use App\Events\ReviewShared;
use App\Events\ReviewSigned;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendReviewNotification
{
    public function handleSubmitted(ReviewSubmitted $event)
    {
        $review = $event->review;
        try {
            if ($review->type === 'self' && $review->reviewee->manager) {
                $manager = $review->reviewee->manager;
                Mail::raw("{$review->reviewee->full_name} has submitted their self-review.", function ($msg) use ($manager) {
                    $msg->to($manager->email)->subject("Self-Review Submitted");
                });
            }
        } catch (\Exception $e) { Log::error($e->getMessage()); }
    }

    public function handleShared(ReviewShared $event)
    {
        $review = $event->review;
        try {
            Mail::raw("Your manager {$review->reviewer->full_name} has shared your performance review.", function ($msg) use ($review) {
                $msg->to($review->reviewee->email)->subject("Performance Review Shared");
            });
        } catch (\Exception $e) { Log::error($e->getMessage()); }
    }

    public function handleSigned(ReviewSigned $event)
    {
        $review = $event->review;
        try {
            if ($review->reviewer) {
                Mail::raw("{$review->reviewee->full_name} has signed their performance review.", function ($msg) use ($review) {
                    $msg->to($review->reviewer->email)->subject("Performance Review Signed");
                });
            }
        } catch (\Exception $e) { Log::error($e->getMessage()); }
    }
}
