<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\OneToOne;
use App\Models\TimeOffRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the system already knows that argues against a proposed time.
 *
 * A general calendar cannot do this. This one holds the rota, the leave book
 * and the holiday calendars, so it can say "she is on annual leave that day"
 * or "that is two hours after his shift ends" before the invitation goes out
 * rather than after somebody fails to turn up.
 *
 * These are warnings, never refusals. A return-to-work conversation is booked
 * precisely for a day somebody is not in, and a manager rearranging a week
 * knows things this does not. The failure worth preventing is not an unusual
 * booking — it is one nobody noticed was unusual.
 */
class MeetingAvailability
{
    public function __construct(
        private ShiftService $shifts,
        private TimezoneService $timezones,
    ) {}

    /**
     * Both sides, each line already saying whose problem it is, so the form can
     * print them without working out who is who.
     */
    public function warnings(User $employee, User $manager, Carbon $at, int $minutes, ?int $ignoreId = null): array
    {
        $lines = [];

        foreach ($this->warningsFor($employee, $at, $minutes, $ignoreId) as $w) {
            $lines[] = $employee->first_name . ' ' . $w;
        }

        // Only worth saying for somebody else — a manager booking their own
        // time does not need telling they are busy with this.
        if ($manager->id !== $employee->id) {
            foreach ($this->warningsFor($manager, $at, $minutes, $ignoreId) as $w) {
                $lines[] = $manager->first_name . ' ' . $w;
            }
        }

        return $lines;
    }

    /**
     * One person. Each line reads as a sentence continuing from their name,
     * e.g. "is on approved Annual Leave that day".
     */
    public function warningsFor(User $user, Carbon $at, int $minutes, ?int $ignoreId = null): array
    {
        $minutes = $minutes ?: OneToOne::DEFAULT_MINUTES;
        $date = $at->copy()->startOfDay();
        $warnings = [];

        if ($leave = $this->leaveOn($user, $date)) {
            $warnings[] = 'is on approved ' . ($leave->policy->name ?? 'leave') . ' that day';
        }

        if ($holiday = $this->holidayOn($user, $date)) {
            $warnings[] = 'has ' . $holiday . ' that day — a public holiday on their calendar';
        }

        $warnings = array_merge($warnings, $this->shiftWarnings($user, $at, $minutes));

        foreach ($this->overlapping($user, $at, $minutes, $ignoreId) as $clash) {
            $warnings[] = 'already has "' . $clash->subject . '" at '
                . $this->timezones->toUserTime($clash->scheduled_at, $user)->format('H:i');
        }

        return $warnings;
    }

    // --- The individual questions -------------------------------------------

    private function leaveOn(User $user, Carbon $date): ?TimeOffRequest
    {
        return TimeOffRequest::with('policy')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();
    }

    private function holidayOn(User $user, Carbon $date): ?string
    {
        $calendars = DB::table('calendar_user')->where('user_id', $user->id)->pluck('calendar_id');

        if ($calendars->isEmpty()) {
            return null;
        }

        return Holiday::whereIn('calendar_id', $calendars)
            ->whereDate('date', $date->toDateString())
            ->value('name');
    }

    /**
     * The rota's view. Compared as instants, because a shift's start and end
     * are wall-clock in the employee's timezone and the stored meeting time is
     * canonical — comparing the two as strings would be right only by accident.
     */
    private function shiftWarnings(User $user, Carbon $at, int $minutes): array
    {
        $expected = $this->shifts->getExpectedTimesForUserOnDate($user, $at->copy()->startOfDay());

        if (! $expected) {
            // No shift at all. Said softly: plenty of people in this product
            // are not on the rota, and for them this means nothing.
            return $this->isOnRota($user)
                ? ['is not rostered to work that day']
                : [];
        }

        $start = $expected['start'];
        $end = $expected['end'];
        $meetingEnd = $at->copy()->addMinutes($minutes);

        if ($at->lt($start) || $meetingEnd->gt($end)) {
            return [sprintf(
                'works %s–%s that day, and this falls outside it',
                $this->timezones->toUserTime($start, $user)->format('H:i'),
                $this->timezones->toUserTime($end, $user)->format('H:i'),
            )];
        }

        return [];
    }

    /** Somebody with any shift assignment is on the rota; most people are not. */
    private function isOnRota(User $user): bool
    {
        return DB::table('shift_assignments')->where('user_id', $user->id)->exists();
    }

    /**
     * Other bookings that run into this one. Only open ones: a cancelled
     * meeting is not occupying anybody's afternoon.
     */
    private function overlapping(User $user, Carbon $at, int $minutes, ?int $ignoreId): \Illuminate\Support\Collection
    {
        $end = $at->copy()->addMinutes($minutes);

        return OneToOne::involving($user)
            ->whereIn('status', ['proposed', 'confirmed'])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            // Same day first so the overlap test runs over a handful of rows
            // rather than the whole history.
            ->whereDate('scheduled_at', $at->toDateString())
            ->get()
            ->filter(fn (OneToOne $m) => $m->scheduled_at->lt($end) && $m->endsAt()->gt($at))
            ->values();
    }
}
