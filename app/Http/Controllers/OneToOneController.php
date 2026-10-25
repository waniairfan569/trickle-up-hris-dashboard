<?php

namespace App\Http\Controllers;

use App\Models\OneToOne;
use App\Models\User;
use App\Notifications\OneToOneAnswered;
use App\Notifications\OneToOneProposed;
use App\Services\MeetingAvailability;
use App\Services\TimezoneService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Booking a conversation, and keeping the record of it.
 *
 * Who may book whom is worked out from the reporting line rather than asked
 * for: a manager books with their own people, an employee asks their own
 * manager, and HR may book on anyone's behalf. The form therefore only needs
 * to know who the other person is, which is also the only thing the person
 * filling it in actually knows.
 */
class OneToOneController extends Controller
{
    public function __construct(
        private MeetingAvailability $availability,
        private TimezoneService $timezones,
    ) {}

    // --- Reading -------------------------------------------------------------

    public function index(Request $request)
    {
        $user = $request->user();

        $mine = OneToOne::involving($user)->with(['employee', 'manager', 'proposer'])
            ->upcoming()->get();

        // Agreed, the time has passed, and nobody has said whether it happened.
        // Shown first because it is the only list that rots if ignored.
        $loose = OneToOne::involving($user)->with(['employee', 'manager'])
            ->unresolved()->get();

        $past = OneToOne::involving($user)->with(['employee', 'manager'])
            ->past()->limit(25)->get();

        // The whole organisation's, for whoever is accountable for it happening.
        $everyones = $user->isAdmin()
            ? OneToOne::with(['employee', 'manager'])->upcoming()->limit(50)->get()
            : collect();

        return view('one-to-ones.index', [
            'mine' => $mine,
            'loose' => $loose,
            'past' => $past,
            'everyones' => $everyones,
            'bookable' => $this->bookableWith($user),
            'canName' => $user->isAdmin(),
            'tzLabel' => $this->timezones->abbreviation($user),
            // Arrived from somewhere that already knows what the conversation
            // is for — the probation list, say. The form opens filled in.
            'prefill' => [
                'with' => $request->integer('with') ?: '',
                'subject' => (string) $request->string('subject'),
                'about_key' => $this->aboutType($request->input('about_key')) ? $request->input('about_key') : '',
                'about_id' => $request->integer('about_id') ?: '',
            ],
        ]);
    }

    public function show(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustBeInvolved($user, $oneToOne);

        $oneToOne->load(['employee', 'manager', 'proposer', 'events.actor', 'about']);

        return view('one-to-ones.show', [
            'meeting' => $oneToOne,
            // The private note belongs to whoever holds the conversation. The
            // employee seeing it would make it a note nobody dares write.
            'seesPrivate' => $user->id === $oneToOne->manager_id || $user->isAdmin(),
            'warnings' => $oneToOne->isOpen() && ! $oneToOne->isPast()
                ? $this->availability->warnings(
                    $oneToOne->employee, $oneToOne->manager,
                    $oneToOne->scheduled_at, $oneToOne->duration_minutes, $oneToOne->id)
                : [],
            'tzLabel' => $this->timezones->abbreviation($user),
        ]);
    }

    /** Live answer for the booking form: what is already known about this time. */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'with_user_id' => 'required|exists:users,id',
            'holder_id' => 'nullable|exists:users,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'ignore' => 'nullable|integer',
        ]);

        [$employee, $holder] = $this->sides(
            $request->user(), (int) $validated['with_user_id'], $validated['holder_id'] ?? null);

        $at = $this->instant($employee, $validated['date'], $validated['time']);

        return response()->json([
            'warnings' => $this->availability->warnings(
                $employee, $holder, $at,
                (int) (($validated['duration_minutes'] ?? null) ?: OneToOne::DEFAULT_MINUTES),
                $validated['ignore'] ?? null,
            ),
        ]);
    }

    /** The viewer's own bookings, as a second feed on the company calendar. */
    public function calendarData(Request $request)
    {
        $user = $request->user();

        $from = $request->filled('start') ? Carbon::parse($request->input('start')) : now()->subMonth();
        $to = $request->filled('end') ? Carbon::parse($request->input('end')) : now()->addMonths(3);

        $meetings = OneToOne::involving($user)->with(['employee', 'manager'])
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->get();

        return response()->json($meetings->map(function (OneToOne $m) use ($user) {
            $other = $m->otherParty($user);

            return [
                'id' => 'o2o-' . $m->id,
                'title' => '1-1 · ' . ($other->first_name ?? 'someone'),
                'start' => $this->timezones->toUserTime($m->scheduled_at, $user)->toIso8601String(),
                'end' => $this->timezones->toUserTime($m->endsAt(), $user)->toIso8601String(),
                // Awaiting an answer looks different from agreed, because one
                // of them is a commitment and the other is only a hope.
                'color' => $m->status === 'confirmed' ? '#059669' : '#D97706',
                'url' => route('one-to-ones.show', $m),
            ];
        })->values());
    }

    // --- Writing -------------------------------------------------------------

    public function store(Request $request)
    {
        $actor = $request->user();

        $validated = $request->validate([
            'with_user_id' => 'required|exists:users,id',
            'holder_id' => 'nullable|exists:users,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'subject' => 'required|string|max:160',
            'agenda' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:160',
            'is_online' => 'nullable|boolean',
            'meeting_link' => 'nullable|url|max:500',
            'about_key' => 'nullable|string|in:probation,pay_review',
            'about_id' => 'nullable|integer',
        ], [
            'date.after_or_equal' => 'A conversation cannot be booked for a day that has already gone.',
        ]);

        [$employee, $holder] = $this->sides(
            $actor, (int) $validated['with_user_id'], $validated['holder_id'] ?? null);

        $at = $this->instant($employee, $validated['date'], $validated['time']);

        // Validation let today through so a meeting can be booked for this
        // afternoon; this catches this morning.
        if ($at->isPast()) {
            return back()->withInput()->with('error', 'That time has already passed today.');
        }

        $meeting = OneToOne::create([
            'user_id' => $employee->id,
            'manager_id' => $holder->id,
            'scheduled_at' => $at,
            'duration_minutes' => ($validated['duration_minutes'] ?? null) ?: OneToOne::DEFAULT_MINUTES,
            'subject' => $validated['subject'],
            'agenda' => $validated['agenda'] ?? null,
            'location' => $validated['location'] ?? null,
            'is_online' => (bool) ($validated['is_online'] ?? false),
            'meeting_link' => $validated['meeting_link'] ?? null,
            'status' => 'proposed',
            'proposed_by' => $actor->id,
            'about_type' => $this->aboutType($validated['about_key'] ?? null),
            'about_id' => $this->aboutType($validated['about_key'] ?? null) ? ($validated['about_id'] ?? null) : null,
            'created_by' => $actor->id,
        ]);

        $meeting->logEvent('proposed', $actor, $validated['agenda'] ?? null, $at);

        $awaiting = $meeting->awaitingAnswerFrom();
        $this->tell($awaiting, new OneToOneProposed($meeting, $actor));

        return redirect()->route('one-to-ones.show', $meeting)
            ->with('success', 'Proposed. ' . ($awaiting->first_name ?? 'They')
                . ' has been asked to agree the time.');
    }

    public function confirm(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustBeInvolved($user, $oneToOne);

        // Only the side that did not propose it. Agreeing with yourself would
        // turn a proposal into a summons.
        if (! $oneToOne->isAwaitingAnswerFrom($user)) {
            return back()->with('error', 'This is waiting on the other person, not on you.');
        }

        $oneToOne->update(['status' => 'confirmed', 'responded_at' => now()]);
        $oneToOne->logEvent('confirmed', $user);

        $this->tell($oneToOne->otherParty($user), new OneToOneAnswered($oneToOne, $user, 'confirmed'));

        return back()->with('success', 'Agreed. It is on both calendars now.');
    }

    public function decline(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustBeInvolved($user, $oneToOne);

        $validated = $request->validate(['note' => 'required|string|max:1000'], [
            'note.required' => 'Say why, so the other person knows what to do next.',
        ]);

        if (! $oneToOne->isOpen()) {
            return back()->with('error', 'This one is already settled.');
        }

        $oneToOne->update(['status' => 'declined', 'responded_at' => now()]);
        $oneToOne->logEvent('declined', $user, $validated['note']);

        $this->tell($oneToOne->otherParty($user), new OneToOneAnswered($oneToOne, $user, 'declined'));

        return back()->with('success', 'Declined, and they have been told why.');
    }

    /**
     * Answer with a different time. The proposal changes hands rather than
     * being replaced, so the other side now owes the answer and the trail
     * still shows what was offered first.
     */
    public function reschedule(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustBeInvolved($user, $oneToOne);

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'note' => 'nullable|string|max:1000',
        ]);

        if (! $oneToOne->isOpen()) {
            return back()->with('error', 'This one is already settled — book a new one.');
        }

        $at = $this->instant($oneToOne->employee, $validated['date'], $validated['time']);

        if ($at->isPast()) {
            return back()->with('error', 'That time has already passed today.');
        }

        $oneToOne->update([
            'scheduled_at' => $at,
            'duration_minutes' => ($validated['duration_minutes'] ?? null) ?: $oneToOne->duration_minutes,
            'status' => 'proposed',
            'proposed_by' => $user->id,
            'responded_at' => null,
        ]);

        $oneToOne->logEvent('rescheduled', $user, $validated['note'] ?? null, $at);

        $awaiting = $oneToOne->awaitingAnswerFrom();
        $this->tell($awaiting, new OneToOneProposed($oneToOne, $user));

        return back()->with('success', 'Another time proposed. It now waits on '
            . ($awaiting->first_name ?? 'them') . '.');
    }

    /** It happened. The outcome is the point of the whole record. */
    public function hold(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustHold($user, $oneToOne);

        $validated = $request->validate([
            'outcome' => 'required|string|max:5000',
            'private_note' => 'nullable|string|max:5000',
        ], [
            'outcome.required' => 'Write what was agreed — an empty record is worse than none.',
        ]);

        if (! $oneToOne->isOpen()) {
            return back()->with('error', 'This one is already closed.');
        }

        $oneToOne->update([
            'status' => 'held',
            'held_at' => now(),
            'outcome' => $validated['outcome'],
            'private_note' => $validated['private_note'] ?? $oneToOne->private_note,
        ]);

        $oneToOne->logEvent('held', $user, $validated['outcome']);
        $this->tell($oneToOne->employee, new OneToOneAnswered($oneToOne, $user, 'held'));

        return back()->with('success', 'Recorded.');
    }

    /** It did not happen, and saying so beats leaving it looking agreed. */
    public function missed(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustHold($user, $oneToOne);

        $validated = $request->validate(['note' => 'nullable|string|max:1000']);

        if (! $oneToOne->isOpen()) {
            return back()->with('error', 'This one is already closed.');
        }

        $oneToOne->update(['status' => 'missed']);
        $oneToOne->logEvent('missed', $user, $validated['note'] ?? null);

        return back()->with('success', 'Marked as not happened.');
    }

    public function cancel(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustBeInvolved($user, $oneToOne);

        $validated = $request->validate(['note' => 'nullable|string|max:1000']);

        if (! $oneToOne->isOpen()) {
            return back()->with('error', 'This one is already settled.');
        }

        $oneToOne->update(['status' => 'cancelled']);
        $oneToOne->logEvent('cancelled', $user, $validated['note'] ?? null);

        $this->tell($oneToOne->otherParty($user), new OneToOneAnswered($oneToOne, $user, 'cancelled'));

        return back()->with('success', 'Cancelled, and the other person has been told.');
    }

    public function note(Request $request, OneToOne $oneToOne)
    {
        $user = $request->user();
        $this->mustBeInvolved($user, $oneToOne);

        $validated = $request->validate(['note' => 'required|string|max:2000']);

        $oneToOne->logEvent('note', $user, $validated['note']);

        return back()->with('success', 'Note added.');
    }

    // --- Who may do what ----------------------------------------------------

    private function mustBeInvolved(User $user, OneToOne $meeting): void
    {
        abort_unless($meeting->involves($user) || $user->isAdmin(), 404);
    }

    /** Recording the outcome belongs to whoever held it, or to HR. */
    private function mustHold(User $user, OneToOne $meeting): void
    {
        abort_unless($user->id === $meeting->manager_id || $user->isAdmin(), 403);
    }

    /**
     * Work out which side is which, and refuse a pairing the reporting line
     * does not support. Returns [the employee, whoever holds it].
     */
    private function sides(User $actor, int $withId, ?string $holderId): array
    {
        $other = User::findOrFail($withId);

        if ($actor->isAdmin()) {
            // HR may say who holds it — a probation review is often the
            // manager's conversation even when HR arranged it.
            $holder = $holderId ? User::findOrFail((int) $holderId) : $actor;
            abort_if($holder->id === $other->id, 422, 'A one-to-one needs two people.');

            return [$other, $holder];
        }

        abort_if($other->id === $actor->id, 422, 'A one-to-one needs two people.');

        if ($actor->managesUser($other->id)) {
            return [$other, $actor];
        }

        if ($actor->allManagerIds()->contains($other->id)) {
            // The employee asking their own manager for a conversation. Read
            // from the actor's own managers, not the other person's.
            return [$actor, $other];
        }

        abort(403, 'You can only book a one-to-one with your own manager, or with somebody who reports to you.');
    }

    /** Everybody this person is allowed to put in the "with" box. */
    private function bookableWith(User $user)
    {
        if ($user->isAdmin()) {
            return User::where('account_status', '!=', 'deactivated')
                ->whereKeyNot($user->id)
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'job_title']);
        }

        $ids = $user->teamMemberIds()->merge($user->allManagerIds())->unique()->all();

        return User::whereIn('id', $ids)
            ->where('account_status', '!=', 'deactivated')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'job_title']);
    }

    /**
     * The only things a conversation can be attached to.
     *
     * A short key rather than a class name, because what comes back from a form
     * or a query string is from the outside world: a raw morph type would let
     * anything at all be loaded as `about`, and no amount of validating a class
     * string is as safe as never accepting one.
     */
    private function aboutType(?string $key): ?string
    {
        return match ($key) {
            'probation' => \App\Models\Probation::class,
            'pay_review' => \App\Models\PayReview::class,
            default => null,
        };
    }

    // --- Time ----------------------------------------------------------------

    /**
     * The typed date and time are the employee's wall clock — that is the clock
     * their shift is written in and the one they will turn up by. Parsing it in
     * their timezone makes the stored instant mean the same thing to both
     * people, however their own timezones happen to be set.
     */
    private function instant(User $employee, string $date, string $time): Carbon
    {
        return Carbon::parse(
            Carbon::parse($date)->toDateString() . ' ' . $time,
            $this->timezones->getEffectiveTimezone($employee),
        );
    }

    private function tell(?User $user, $notification): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            // A conversation being booked matters more than the email about it.
            report($e);
        }
    }
}
