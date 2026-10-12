<?php

namespace App\Http\Controllers;

use App\Models\TimeOffRequest;
use Illuminate\Http\Request;

/**
 * The employee's own note on returning from an unplanned absence, written from
 * their portal rather than in a return-to-work meeting. Mirrors the lateness
 * prompt: asked once, answered once, and read by the month-end document.
 */
class LeaveReturnNoteController extends Controller
{
    /**
     * Save a note against one or more leaves. The prompt submits every
     * outstanding leave at once, and blank ones are simply left for later.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'notes'   => 'required|array|max:60',
            'notes.*' => 'nullable|string|max:1000',
        ], [
            'notes.required' => 'Add a note for at least one leave.',
        ]);

        // Only leaves that are actually this employee's, back from, and unanswered.
        $outstanding = TimeOffRequest::outstandingReturnsFor($user)->keyBy('id');

        $saved = 0;
        foreach ($validated['notes'] as $leaveId => $note) {
            $note = trim((string) $note);
            $leave = $outstanding->get((int) $leaveId);
            if ($note === '' || ! $leave) {
                continue;
            }

            $leave->forceFill([
                'return_note'    => $note,
                'return_note_at' => now(),
                'return_note_ip' => $request->ip(),
            ])->save();
            $saved++;
        }

        if ($saved === 0) {
            return back()->with('error', 'Nothing was saved — add a note for at least one leave.');
        }

        $remaining = TimeOffRequest::outstandingReturnsFor($user)->count();

        return back()->with('success', $remaining > 0
            ? "Thanks — {$saved} answered, {$remaining} still to go."
            : 'Thanks — that is everything answered.');
    }

    /**
     * Dismiss the prompt for now. Nothing is stored: the leaves stay
     * outstanding and the banner keeps showing, so an answer is only postponed.
     */
    public function snooze(Request $request)
    {
        $request->session()->put('return_prompt_snoozed_until', now()->addHours(4)->timestamp);

        return back();
    }

    /** The employee's own history of the returns they have written up. */
    public function index(Request $request)
    {
        $user = $request->user();

        return view('leave-returns.index', [
            'outstanding' => TimeOffRequest::outstandingReturnsFor($user),
            'answered'    => TimeOffRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->returnToWorkEligible()
                ->whereNotNull('return_note')
                ->with('policy')
                ->orderByDesc('end_date')
                ->limit(100)
                ->get(),
        ]);
    }
}
