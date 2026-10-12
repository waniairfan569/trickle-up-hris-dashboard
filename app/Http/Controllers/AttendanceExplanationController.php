<?php

namespace App\Http\Controllers;

use App\Models\AttendanceExplanation;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;

/** The employee's own account of why they were late, given on the day. */
class AttendanceExplanationController extends Controller
{
    /**
     * Save a reason for one or more late days. The prompt submits every
     * outstanding day at once, and blank ones are simply left for later.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'reasons'   => 'required|array|max:60',
            'reasons.*' => 'nullable|string|max:500',
        ], [
            'reasons.required' => 'Add a reason for at least one day.',
        ]);

        // Only days that are actually this employee's, late, and unexplained.
        $outstanding = AttendanceExplanation::outstandingFor($user)->keyBy('id');

        $saved = 0;
        foreach ($validated['reasons'] as $recordId => $reason) {
            $reason = trim((string) $reason);
            $record = $outstanding->get((int) $recordId);
            if ($reason === '' || ! $record) {
                continue;
            }

            AttendanceExplanation::create([
                'user_id'              => $user->id,
                'attendance_record_id' => $record->id,
                'date'                 => $record->date->toDateString(),
                'type'                 => 'late',
                'late_minutes'         => (int) $record->late_minutes,
                'reason'               => $reason,
                'acknowledged_at'      => now(),
                'acknowledged_ip'      => $request->ip(),
            ]);
            $saved++;
        }

        if ($saved === 0) {
            return back()->with('error', 'Nothing was saved — add a reason for at least one day.');
        }

        $remaining = AttendanceExplanation::outstandingFor($user)->count();

        return back()->with('success', $remaining > 0
            ? "Thanks — {$saved} explained, {$remaining} still to go."
            : 'Thanks — that is everything explained.');
    }

    /**
     * Dismiss the prompt for now. Nothing is stored: the days stay outstanding
     * and the banner keeps showing, so a reason is only ever postponed.
     */
    public function snooze(Request $request)
    {
        $request->session()->put('lateness_prompt_snoozed_until', now()->addHours(4)->timestamp);

        return back();
    }

    /** The employee's own history of what they have explained. */
    public function index(Request $request)
    {
        $user = $request->user();

        return view('attendance-explanations.index', [
            'outstanding' => AttendanceExplanation::outstandingFor($user),
            'explained'   => AttendanceExplanation::where('user_id', $user->id)
                ->with('document')->orderByDesc('date')->limit(100)->get(),
        ]);
    }
}
