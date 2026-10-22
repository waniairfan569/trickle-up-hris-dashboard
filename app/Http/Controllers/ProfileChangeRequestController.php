<?php

namespace App\Http\Controllers;

use App\Models\ProfileChangeRequest;
use App\Services\ProfileChangeGuard;
use Illuminate\Http\Request;

/**
 * The queue of record changes employees have asked for and HR has to answer.
 *
 * Only fields a workspace has marked "HR approves first" arrive here; until
 * one is set, this page is simply empty.
 */
class ProfileChangeRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeReview();

        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'pending';

        $requests = ProfileChangeRequest::with(['employee', 'field.section', 'requester', 'reviewer'])
            ->where('status', $status)
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('profile-changes.index', [
            'requests' => $requests,
            'status'   => $status,
            'counts'   => [
                'pending'  => ProfileChangeRequest::where('status', 'pending')->count(),
                'approved' => ProfileChangeRequest::where('status', 'approved')->count(),
                'rejected' => ProfileChangeRequest::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function decide(Request $request, ProfileChangeRequest $profileChange)
    {
        $this->authorizeReview();

        $validated = $request->validate([
            'decision' => 'required|in:approve,reject',
            'note'     => 'nullable|string|max:500',
        ]);

        if (! $profileChange->isPending()) {
            return back()->with('error', 'That change has already been answered.');
        }

        $approve = $validated['decision'] === 'approve';

        app(ProfileChangeGuard::class)->decide(
            $profileChange, $request->user(), $approve, $validated['note'] ?? null
        );

        return back()->with('success', $approve
            ? "Approved — “{$profileChange->label()}” has been updated on their record."
            : "Turned down — “{$profileChange->label()}” is unchanged, and they have been told.");
    }

    private function authorizeReview(): void
    {
        $user = auth()->user();

        abort_unless($user && $user->isAdmin(), 403, 'Only HR administrators can review record changes.');
    }
}
