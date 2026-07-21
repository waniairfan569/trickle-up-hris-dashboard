<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ReviewCycle;
use App\Models\PerformanceReview;
use App\Events\ReviewSubmitted;
use App\Events\ReviewShared;
use App\Events\ReviewSigned;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $activeCycles = ReviewCycle::active()->get();
        $mySelfReviews = PerformanceReview::where('reviewee_id', $user->id)
            ->where('type', 'self')
            ->with('cycle')
            ->get();
            
        $myManagerReviews = PerformanceReview::where('reviewee_id', $user->id)
            ->where('type', 'manager')
            ->whereIn('status', ['shared', 'signed']) // Only see manager review if shared or signed
            ->with(['cycle', 'reviewer'])
            ->get();

        $teamReviews = collect();
        if ($user->isManager()) {
            $directReportIds = $user->teamMemberIds();
            $teamReviews = PerformanceReview::whereIn('reviewee_id', $directReportIds)
                ->with(['cycle', 'reviewee'])
                ->get();
        }

        $allReviews = collect();
        if ($user->isAdmin()) {
            $allReviews = PerformanceReview::with(['cycle', 'reviewee', 'reviewer'])->get();
        }

        return view('performance.index', compact('activeCycles', 'mySelfReviews', 'myManagerReviews', 'teamReviews', 'allReviews'));
    }

    /**
     * Display the specified resource.
     */
    public function show(PerformanceReview $performance)
    {
        if (!$performance->canBeViewedBy(auth()->user())) {
            abort(403, 'Unauthorized action.');
        }

        return view('performance.review', compact('performance'));
    }

    /**
     * Store or update a self-review.
     */
    public function storeSelfReview(Request $request)
    {
        $validated = $request->validate([
            'cycle_id' => 'required|exists:review_cycles,id',
            'action' => 'required|in:save,submit',
            'achievements' => 'nullable|string',
            'goals' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $cycle = ReviewCycle::findOrFail($validated['cycle_id']);
        $user = $request->user();

        $review = PerformanceReview::firstOrNew([
            'cycle_id' => $cycle->id,
            'reviewee_id' => $user->id,
            'type' => 'self',
        ]);

        if ($review->exists && !$review->canBeEditedBy($user)) {
            abort(403, 'Review can no longer be edited.');
        }

        $review->content = [
            'achievements' => $validated['achievements'] ?? null,
            'goals' => $validated['goals'] ?? null,
            'rating' => $validated['rating'] ?? null,
        ];

        if ($validated['action'] === 'submit') {
            $review->status = 'submitted';
            $review->submitted_at = now();
        }

        $review->save();

        if ($review->status === 'submitted') {
            event(new ReviewSubmitted($review));
            return redirect()->route('performance.index')->with('success', 'Self-review submitted successfully.');
        }

        return redirect()->route('performance.show', $review)->with('success', 'Draft saved successfully.');
    }

    /**
     * Store or update a manager review.
     */
    public function storeManagerReview(Request $request, User $reviewee)
    {
        $manager = $request->user();
        if (!$manager->canManage($reviewee) && !$manager->isAdmin()) {
            abort(403, 'You are not authorized to review this employee.');
        }

        $validated = $request->validate([
            'cycle_id' => 'required|exists:review_cycles,id',
            'action' => 'required|in:save,submit',
            'feedback' => 'nullable|string',
            'strengths' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $cycle = ReviewCycle::findOrFail($validated['cycle_id']);

        $review = PerformanceReview::firstOrNew([
            'cycle_id' => $cycle->id,
            'reviewee_id' => $reviewee->id,
            'type' => 'manager',
        ]);

        if (!$review->exists) {
            $review->reviewer_id = $manager->id;
        }

        if ($review->exists && !$review->canBeEditedBy($manager)) {
            abort(403, 'Review can no longer be edited.');
        }

        $review->content = [
            'feedback' => $validated['feedback'] ?? null,
            'strengths' => $validated['strengths'] ?? null,
            'areas_for_improvement' => $validated['areas_for_improvement'] ?? null,
            'rating' => $validated['rating'] ?? null,
        ];

        if ($validated['action'] === 'submit') {
            $review->status = 'submitted';
            $review->submitted_at = now();
        }

        $review->save();

        if ($review->status === 'submitted') {
            event(new ReviewSubmitted($review));
            return redirect()->route('performance.index')->with('success', 'Manager review submitted successfully.');
        }

        return redirect()->route('performance.show', $review)->with('success', 'Draft saved successfully.');
    }

    /**
     * Share a manager review with the employee.
     */
    public function share(PerformanceReview $review)
    {
        $user = auth()->user();
        if ($review->type !== 'manager' || $review->reviewer_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
        if ($review->status !== 'submitted') {
            return back()->withErrors(['error' => 'Review must be submitted before sharing.']);
        }

        $review->status = 'shared';
        $review->shared_at = now();
        $review->save();

        event(new ReviewShared($review));

        return back()->with('success', 'Review shared with employee.');
    }

    /**
     * Employee signs the review.
     */
    public function sign(PerformanceReview $review)
    {
        $user = auth()->user();
        if ($review->type !== 'manager' || $review->reviewee_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
        if ($review->status !== 'shared') {
            return back()->withErrors(['error' => 'Review must be shared before signing.']);
        }

        $review->status = 'signed';
        $review->signed_at = now();
        $review->save();

        event(new ReviewSigned($review));

        return back()->with('success', 'Review signed successfully.');
    }

    /**
     * Admin reopens a review.
     */
    public function reopen(PerformanceReview $review)
    {
        $user = auth()->user();
        if (!$user->hasRole('super_admin')) {
            abort(403, 'Unauthorized.');
        }

        $review->status = 'draft';
        $review->reopened_by = $user->id;
        $review->submitted_at = null;
        $review->shared_at = null;
        $review->signed_at = null;
        $review->save();

        return back()->with('success', 'Review reopened to draft status.');
    }

    public function storeCycle(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $validated['status'] = 'active';
        $validated['created_by'] = auth()->id();

        \App\Models\ReviewCycle::create($validated);

        return redirect()->route('performance.index')->with('success', 'Review cycle created successfully.');
    }
}
