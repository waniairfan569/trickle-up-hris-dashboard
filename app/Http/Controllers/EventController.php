<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Event;
use App\Models\User;
use App\Notifications\EventPublishedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EventController extends Controller
{
    // ---- Admin --------------------------------------------------------------

    /** Admin management page — calendar + list of active events, plus archive. */
    public function index(Request $request)
    {
        $this->authorizeManage();

        // Month filter (YYYY-MM): events whose date (or end date) falls in the month.
        $month = $request->input('month');
        if ($month && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = null;
        }
        $monthFilter = null;
        if ($month) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $from = $start->copy()->startOfMonth()->toDateString();
            $to   = $start->copy()->endOfMonth()->toDateString();
            $monthFilter = fn ($q) => $q->where(function ($qq) use ($from, $to) {
                $qq->whereBetween('date', [$from, $to])->orWhereBetween('end_date', [$from, $to]);
            });
        }

        $events = Event::where('status', 'active')->when($monthFilter, $monthFilter)->orderBy('date')->get();
        $archived = Event::where('status', 'archived')->when($monthFilter, $monthFilter)->orderByDesc('date')->get();
        $departments = Department::orderBy('name')->get(['id', 'name']);
        $users = User::where('account_status', '!=', 'deactivated')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        return view('events.index', compact('events', 'archived', 'departments', 'users', 'month'));
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $data = $this->validateEvent($request);

        $event = Event::create($data + [
            'company_id' => auth()->user()->company_id,
            'created_by' => auth()->id(),
            'status' => 'active',
            'is_published' => false,
        ]);

        $this->syncAudiences($event, $request);

        if ($request->boolean('publish_now')) {
            $event->publish(auth()->user());
            $count = $this->notifyRecipients($event);

            return back()->with('success', 'Event added and published to employees'
                . ($count ? " — {$count} notified." : '.'));
        }

        return back()->with('success', 'Event saved as a draft — publish it when you’re ready.');
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeManage();
        $event->update($this->validateEvent($request));
        $this->syncAudiences($event, $request);

        return back()->with('success', 'Event updated.');
    }

    /** AJAX: publish an event to employees (and notify them). */
    public function publish(Event $event)
    {
        $this->authorizeManage();
        $event->publish(auth()->user());
        $count = $this->notifyRecipients($event);

        return response()->json([
            'success' => true,
            'message' => 'Event published to employees' . ($count ? " ({$count} notified)" : ''),
            'published_at' => $event->published_at->format('d M Y H:i'),
        ]);
    }

    /** AJAX: hide an event from employees again. */
    public function unpublish(Event $event)
    {
        $this->authorizeManage();
        $event->unpublish();

        return response()->json(['success' => true, 'message' => 'Event hidden from employees']);
    }

    /** AJAX: pin / unpin an event to the employee dashboard. */
    public function togglePin(Event $event)
    {
        $this->authorizeManage();
        $event->is_pinned = ! $event->is_pinned;
        $event->save();

        return response()->json(['success' => true, 'pinned' => (bool) $event->is_pinned]);
    }

    /** JSON feed for the admin calendar — every active event (published + draft). */
    public function calendarData(Request $request)
    {
        $this->authorizeManage();

        $events = $this->windowedEvents(Event::active(), $request);

        return response()->json($events->map(fn ($e) => $this->fcEvent($e))->values());
    }

    public function archive(Event $event)
    {
        $this->authorizeManage();
        $event->update(['status' => 'archived']);

        return back()->with('success', 'Event archived.');
    }

    public function restore(Event $event)
    {
        $this->authorizeManage();
        $event->update(['status' => 'active']);

        return back()->with('success', 'Event restored.');
    }

    public function destroy(Event $event)
    {
        $this->authorizeManage();
        $title = $event->title;
        $event->delete();

        return back()->with('success', "“{$title}” deleted.");
    }

    // ---- Employee -----------------------------------------------------------

    /** Employee calendar — only the events they're allowed to see. */
    public function employeeCalendar()
    {
        $user = auth()->user();

        // Pinned, still-upcoming events surface above the calendar.
        $pinned = Event::active()->visibleTo($user)->where('is_pinned', true)
            ->where(function ($q) {
                $q->whereDate('date', '>=', today())->orWhereDate('end_date', '>=', today());
            })
            ->orderBy('date')->get();

        return view('events.employee-calendar', compact('pinned'));
    }

    /** JSON feed for the employee calendar — published events visible to them. */
    public function employeeCalendarData(Request $request)
    {
        $user = auth()->user();

        $events = $this->windowedEvents(Event::active()->published()->visibleTo($user), $request);

        return response()->json($events->map(fn ($e) => $this->fcEvent($e))->values());
    }

    // ---- Helpers ------------------------------------------------------------

    /** Shape one event the way FullCalendar expects. */
    private function fcEvent(Event $e): array
    {
        // FullCalendar treats an all-day `end` as EXCLUSIVE, so add a day to make
        // a multi-day event render through its end date.
        $end = $e->end_date ? $e->end_date->copy()->addDay()->toDateString() : null;

        return [
            'id' => $e->id,
            'title' => $e->title,
            'start' => $e->date->toDateString(),
            'end' => $end,
            'color' => $e->color_hex,
            'extendedProps' => [
                'location' => $e->location,
                'description' => $e->description,
                'is_published' => (bool) $e->is_published,
                'is_pinned' => (bool) $e->is_pinned,
                'visibility' => $e->visibility,
                'published_at' => optional($e->published_at)->format('d M Y H:i'),
                'end_display' => optional($e->end_date)->toDateString(),
            ],
        ];
    }

    /** Restrict to the calendar's visible window when FullCalendar sends one. */
    private function windowedEvents(Builder $query, Request $request)
    {
        if ($request->filled('start') && $request->filled('end')) {
            $start = substr((string) $request->query('start'), 0, 10);
            $end = substr((string) $request->query('end'), 0, 10);
            $query->where(function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereDate('date', '<=', $start)->whereDate('end_date', '>=', $end);
                    });
            });
        }

        return $query->orderBy('date')->get();
    }

    /** Active employees who should see (and be notified about) this event. */
    private function recipientsFor(Event $event)
    {
        $query = User::where('account_status', 'active')->where('id', '!=', auth()->id());

        if ($event->visibility === 'department') {
            $deptIds = $event->audiences()->where('audience_type', 'department')->pluck('audience_id')->all();
            $query->whereIn('department_id', $deptIds ?: [-1]);
        } elseif ($event->visibility === 'specific') {
            $userIds = $event->audiences()->where('audience_type', 'user')->pluck('audience_id')->all();
            $query->whereIn('id', $userIds ?: [-1]);
        }

        return $query->get();
    }

    /** Notify the event's audience; returns how many were notified. */
    private function notifyRecipients(Event $event): int
    {
        if (! $event->notify_employees) {
            return 0;
        }

        $recipients = $this->recipientsFor($event);
        foreach ($recipients as $user) {
            try {
                $user->notify(new EventPublishedNotification($event));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $recipients->count();
    }

    /** Replace the event's department/user audience rows to match the request. */
    private function syncAudiences(Event $event, Request $request): void
    {
        $event->audiences()->delete();

        if ($event->visibility === 'department') {
            foreach ((array) $request->input('department_ids', []) as $id) {
                if ($id) {
                    $event->audiences()->create(['audience_type' => 'department', 'audience_id' => (int) $id]);
                }
            }
        } elseif ($event->visibility === 'specific') {
            foreach ((array) $request->input('user_ids', []) as $id) {
                if ($id) {
                    $event->audiences()->create(['audience_type' => 'user', 'audience_id' => (int) $id]);
                }
            }
        }
    }

    private function validateEvent(Request $request): array
    {
        $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:date',
            'location' => 'nullable|string|max:120',
            'color' => 'nullable|string|max:20',
            'visibility' => 'nullable|in:all,department,specific',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
        ]);

        return [
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'date' => $request->input('date'),
            'end_date' => $request->input('end_date'),
            'location' => $request->input('location'),
            'color' => $request->input('color') ?: 'brand',
            'visibility' => $request->input('visibility') ?: 'all',
            'notify_employees' => $request->boolean('notify_employees'),
            'is_pinned' => $request->boolean('is_pinned'),
        ];
    }

    private function authorizeManage(): void
    {
        $auth = auth()->user();
        abort_unless($auth && $auth->isAdmin(), 403, 'Only HR administrators can manage events.');
    }
}
