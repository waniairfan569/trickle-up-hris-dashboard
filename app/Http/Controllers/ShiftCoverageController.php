<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftCoverageAlert;
use App\Models\User;
use App\Services\ShiftCoverageService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The rota holes, and filling them.
 *
 * Filling one writes a single-day assignment — the same thing the shift pages
 * have always written, and the thing lateness and the schedule already read.
 * Nothing about anybody's usual pattern changes, which is the whole point: a
 * cover shift should not quietly become somebody's new Tuesday.
 */
class ShiftCoverageController extends Controller
{
    public function __construct(private ShiftCoverageService $coverage) {}

    public function index(Request $request)
    {
        $days = (int) $request->integer('days', ShiftCoverageService::DEFAULT_HORIZON);
        $days = max(1, min(90, $days));

        $from = $request->filled('from')
            ? Carbon::parse($request->date('from'))->startOfDay()
            : today();

        $to = $from->copy()->addDays($days);

        $gaps = $this->coverage->gaps($from, $to);

        return view('shifts.coverage', [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'gaps' => $gaps,
            'open' => $gaps->filter(fn ($g) => ! $g->cover)->count(),
            'byDate' => $gaps->groupBy(fn ($g) => $g->date->toDateString()),
        ]);
    }

    /** Who could take one particular shift on one particular day. */
    public function candidates(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'instead_of' => 'nullable|exists:users,id',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();
        $shift = Shift::findOrFail($validated['shift_id']);
        $instead = isset($validated['instead_of']) ? User::find($validated['instead_of']) : null;

        return response()->json(
            $this->coverage->candidates($date, $shift, $instead)->map(fn ($c) => [
                'id' => $c->user->id,
                'name' => trim(($c->user->first_name ?? '') . ' ' . ($c->user->last_name ?? '')),
                'free' => $c->free,
                'own_shift' => $c->own_shift?->name,
                'note' => $c->free
                    ? 'Nothing rostered'
                    : ($c->clashes ? 'Already on ' . $c->own_shift->name : 'Already on this shift'),
            ])->values()
        );
    }

    public function cover(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'shift_id' => 'required|exists:shifts,id',
            'covers_user_id' => 'required|exists:users,id',
            'user_id' => 'required|exists:users,id|different:covers_user_id',
            'notes' => 'nullable|string|max:500',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        // One cover per hole. Asking twice replaces the first choice rather
        // than stacking two people on the same absence.
        ShiftAssignment::where('assignment_type', 'single')
            ->whereDate('date', $date)
            ->where('covers_user_id', $validated['covers_user_id'])
            ->delete();

        ShiftAssignment::create([
            'user_id' => $validated['user_id'],
            'shift_id' => $validated['shift_id'],
            'assigned_by' => auth()->id(),
            'covers_user_id' => $validated['covers_user_id'],
            'assignment_type' => 'single',
            'date' => $date,
            'notes' => $validated['notes'] ?? null,
        ]);

        // The hole is closed, so forget it was reported. If the cover is later
        // removed the watcher will raise it again.
        ShiftCoverageAlert::where('user_id', $validated['covers_user_id'])
            ->where('shift_id', $validated['shift_id'])
            ->whereDate('date', $date)
            ->delete();

        $who = User::find($validated['user_id']);

        return back()->with('success', trim(($who->first_name ?? '') . ' ' . ($who->last_name ?? ''))
            . ' is covering ' . Carbon::parse($date)->format('D j M') . '. Their usual shifts are unchanged.');
    }

    public function uncover(ShiftAssignment $assignment)
    {
        abort_unless($assignment->covers_user_id && $assignment->assignment_type === 'single', 404);

        $assignment->delete();

        return back()->with('success', 'Cover removed. The shift is open again.');
    }
}
