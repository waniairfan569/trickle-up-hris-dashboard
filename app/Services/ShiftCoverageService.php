<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\TimeOffRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where the rota has a hole, and who could fill it.
 *
 * The rota and the leave book already knew everything needed to answer this:
 * somebody is on an Early shift every Tuesday, and somebody approved their
 * leave for a fortnight. Nothing joined the two up, so an approved request
 * quietly emptied a shift and the first anybody noticed was the morning it
 * happened.
 *
 * Covering is deliberately not a new concept. A single-day assignment already
 * beats a recurring one everywhere shifts are read, so putting somebody on a
 * date is all the rota needs; this only records who they are standing in for.
 *
 * Reads in bulk rather than asking ShiftService per person per day, because a
 * fortnight across a few dozen people is several hundred lookups. The two must
 * agree — a coverage page that disagrees with lateness is worse than none —
 * so the precedence here mirrors getShiftForUserOnDate exactly, and a test
 * compares the two answer for answer across a range.
 */
class ShiftCoverageService
{
    /** Days ahead the page and the watcher look by default. */
    public const DEFAULT_HORIZON = 14;

    /**
     * Every shift in the range whose assigned person is away.
     *
     * Each row: date, user, shift, why they are away, the leave behind it, and
     * the cover assignment if one has been made.
     */
    public function gaps(Carbon $from, Carbon $to): Collection
    {
        $users = $this->rotaPopulation();

        if ($users->isEmpty()) {
            return collect();
        }

        $ids = $users->keys()->all();
        $assignments = $this->assignmentsIn($ids, $from, $to);
        $leave = $this->leaveIn($ids, $from, $to);
        $holidays = $this->holidaysIn($ids, $from, $to);
        $cover = $this->coverIn($from, $to);

        $rows = collect();

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $key = $date->toDateString();

            foreach ($users as $userId => $user) {
                $shift = $this->shiftOn($assignments->get($userId) ?? collect(), $date);

                if (! $shift) {
                    continue;
                }

                // Closed that day anyway — there is no hole to fill.
                if (in_array($key, $holidays->get($userId, []), true)) {
                    continue;
                }

                $away = ($leave->get($userId) ?? collect())
                    ->first(fn ($r) => $key >= $r->start_date->toDateString()
                        && $key <= $r->end_date->toDateString());

                if (! $away) {
                    continue;
                }

                $rows->push((object) [
                    'date'     => $date->copy(),
                    'user'     => $user,
                    'shift'    => $shift,
                    'leave'    => $away,
                    'reason'   => $away->policy->name ?? 'Leave',
                    'part_day' => $away->duration_type !== 'full_day',
                    'cover'    => $cover->get($key . '|' . $userId),
                ]);
            }
        }

        return $rows->values();
    }

    /** The gaps nobody has filled yet. */
    public function openGaps(Carbon $from, Carbon $to): Collection
    {
        return $this->gaps($from, $to)->filter(fn ($g) => ! $g->cover)->values();
    }

    /**
     * Who could take this shift on this date.
     *
     * Anybody not themselves away. Somebody with no shift that day is listed
     * first, because asking them does not open a second hole.
     */
    public function candidates(Carbon $date, Shift $shift, ?User $instead = null): Collection
    {
        $users = $this->rotaPopulation();
        $ids = $users->keys()->all();
        $assignments = $this->assignmentsIn($ids, $date, $date);
        $leave = $this->leaveIn($ids, $date, $date);
        $key = $date->toDateString();

        return $users->reject(fn ($u) => $instead && $u->id === $instead->id)
            ->map(function (User $user) use ($assignments, $leave, $date, $key, $shift) {
                $own = $this->shiftOn($assignments->get($user->id) ?? collect(), $date);
                $away = ($leave->get($user->id) ?? collect())
                    ->first(fn ($r) => $key >= $r->start_date->toDateString()
                        && $key <= $r->end_date->toDateString());

                return (object) [
                    'user'      => $user,
                    'own_shift' => $own,
                    'away'      => $away,
                    'free'      => ! $own && ! $away,
                    'clashes'   => $own && $own->id !== $shift->id,
                ];
            })
            ->reject(fn ($c) => (bool) $c->away)
            ->sortByDesc(fn ($c) => $c->free ? 1 : 0)
            ->values();
    }

    // ---- the bulk reads ----

    /**
     * Employees a rota applies to: real people, still here. System accounts
     * are excluded for the same reason the attendance pages exclude them.
     */
    private function rotaPopulation(): Collection
    {
        $systemUserIds = Employee::where('is_system', true)->pluck('user_id')->filter()->all();

        return User::where('account_status', '!=', 'deactivated')
            ->whereNotIn('id', $systemUserIds ?: [0])
            ->orderBy('first_name')
            ->get()
            ->keyBy('id');
    }

    /**
     * Ordinary assignments in the window. Cover assignments are left out: they
     * answer "is this hole filled", not "whose shift is this normally".
     */
    private function assignmentsIn(array $userIds, Carbon $from, Carbon $to): Collection
    {
        return ShiftAssignment::with('shift')
            ->whereIn('user_id', $userIds)
            ->whereNull('covers_user_id')
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($q) use ($from, $to) {
                    $q->where('assignment_type', 'single')
                        ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
                })->orWhere(function ($q) use ($to) {
                    $q->where('assignment_type', 'recurring')
                        ->where(fn ($q) => $q->whereNull('recurring_start_date')
                            ->orWhereDate('recurring_start_date', '<=', $to->toDateString()));
                });
            })
            ->get()
            ->filter(fn ($a) => $a->shift && $a->shift->is_active)
            ->groupBy('user_id');
    }

    private function leaveIn(array $userIds, Carbon $from, Carbon $to): Collection
    {
        return TimeOffRequest::with('policy')
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', $from->toDateString())
            ->whereDate('start_date', '<=', $to->toDateString())
            ->get()
            ->groupBy('user_id');
    }

    /** Date strings per user that their own holiday calendar marks as closed. */
    private function holidaysIn(array $userIds, Carbon $from, Carbon $to): Collection
    {
        $calendars = DB::table('calendar_user')
            ->whereIn('user_id', $userIds)
            ->pluck('calendar_id', 'user_id');

        if ($calendars->isEmpty()) {
            return collect();
        }

        $dates = Holiday::whereIn('calendar_id', $calendars->unique()->values())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('calendar_id')
            ->map(fn ($rows) => $rows->map(fn ($h) => $h->date->toDateString())->all());

        return $calendars->map(fn ($calendarId) => $dates->get($calendarId, []));
    }

    /** Cover already arranged, keyed "date|who it is for". */
    private function coverIn(Carbon $from, Carbon $to): Collection
    {
        return ShiftAssignment::with(['user', 'shift'])
            ->whereNotNull('covers_user_id')
            ->where('assignment_type', 'single')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString() . '|' . $a->covers_user_id);
    }

    /**
     * The shift somebody is on for one date.
     *
     * Mirrors ShiftService::getShiftForUserOnDate — single-day assignment
     * first, then a recurring one whose window covers the date and whose days
     * include it. Change one of these and change the other.
     */
    private function shiftOn(Collection $assignments, Carbon $date): ?Shift
    {
        $key = $date->toDateString();

        $single = $assignments->first(fn ($a) => $a->assignment_type === 'single'
            && $a->date?->toDateString() === $key);

        if ($single) {
            return $single->shift;
        }

        $day = $date->format('D');

        $recurring = $assignments->first(fn ($a) => $a->assignment_type === 'recurring'
            && (! $a->recurring_start_date || $a->recurring_start_date->toDateString() <= $key)
            && (! $a->recurring_end_date || $a->recurring_end_date->toDateString() >= $key)
            && in_array($day, $a->recurring_days ?? [], true));

        return $recurring?->shift;
    }
}
