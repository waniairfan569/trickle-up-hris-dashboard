@extends('layouts.hr-app')

@section('title', 'Admin Dashboard')
@section('breadcrumb', '')

@section('content')
@php
    // Real employees only — system/owner accounts are never expected to clock in
    // and never counted absent.
    $realEmployeeIds = \App\Models\Employee::real()->pluck('user_id')->filter();

    // Employees hidden from every attendance sheet/report — never shown as
    // absent or late on the dashboard.
    $attendanceHiddenIds = \App\Models\User::attendanceHiddenIds();

    $pendingApprovals = \App\Models\TimeOffRequest::where('status', 'pending')->count();

    // Today's snapshot. Work From Home is not leave — those people are working
    // (remotely) and still expected to clock in, so they're not counted here.
    $leaveToday = \App\Models\TimeOffRequest::where('status', 'approved')
        ->excludingWorkFromHome()
        ->whereDate('start_date', '<=', today())
        ->whereDate('end_date', '>=', today())
        ->with('policy')->get();
    $isUnplanned = fn ($r) => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower(optional($r->policy)->name ?? ''), ['casual', 'unplanned', 'sick', 'emergency']);
    $unplannedLeave = $leaveToday->filter($isUnplanned)->pluck('user_id')->unique()->count();
    $plannedLeave = $leaveToday->reject($isUnplanned)->pluck('user_id')->unique()->count();
    $onLeaveIds = $leaveToday->pluck('user_id')->unique();

    $todayRecs = \App\Models\AttendanceRecord::whereDate('date', today())
        ->whereNotIn('user_id', $attendanceHiddenIds->all())->get();
    $lateToday = $todayRecs->where('status', 'late')->count();
    $presentIds = $todayRecs->whereIn('status', ['present', 'late', 'overtime', 'early_departure'])->pluck('user_id')->unique();

    $workingRemotely = \App\Models\User::active()
        ->whereIn('id', $realEmployeeIds->all())
        ->where('attendance_mode', 'remote')->count();

    // Absent = active, not present, not on leave — but ONLY on a working day for
    // that employee AND only once their shift has actually STARTED. Someone whose
    // shift begins at 13:30 is NOT "absent" at 11:00 — their working day hasn't
    // begun yet. Today's assigned shift drives the working-day + start-time check;
    // we fall back to their work schedule / weekday when no shift is assigned.
    $shiftSvc = app(\App\Services\ShiftService::class);
    $tzSvc = app(\App\Services\TimezoneService::class);
    $todayDate = today();

    $absentUserIds = \App\Models\User::active()
        ->whereIn('id', $realEmployeeIds->all())
        ->whereNotIn('id', $attendanceHiddenIds->all())
        ->whereNotIn('id', $presentIds->all())
        ->whereNotIn('id', $onLeaveIds->all())
        ->with('workSchedule')
        ->get()
        ->filter(function ($u) use ($shiftSvc, $tzSvc, $todayDate) {
            $shift = $shiftSvc->getShiftForUserOnDate($u, $todayDate->copy());
            if ($shift) {
                $startStr = $shift->start_time;               // has a shift today
            } else {
                $isWorkingDay = $u->workSchedule ? $u->workSchedule->isWorkingDay($todayDate) : !$todayDate->isWeekend();
                if (!$isWorkingDay) {
                    return false;                             // off today
                }
                $startStr = optional($u->workSchedule)->start_time ?: (config('attendance.late_after') ?: '09:30');
            }

            // Not absent until their shift start has passed (in their own timezone).
            $nowUser = $tzSvc->toUserTime(now(), $u);
            $start = \Carbon\Carbon::parse($todayDate->toDateString() . ' ' . $startStr, $nowUser->getTimezone());

            return $nowUser->greaterThanOrEqualTo($start);
        })
        ->pluck('id');
    $absentToday = $absentUserIds->count();

    // Who's in each category — name + avatar + initials for the avatar stack & popup.
    $plannedUserIds = $leaveToday->reject($isUnplanned)->pluck('user_id')->unique();
    $unplannedUserIds = $leaveToday->filter($isUnplanned)->pluck('user_id')->unique();
    $lateUserIds = $todayRecs->where('status', 'late')->pluck('user_id')->unique();
    $asPerson = fn ($u) => ['id' => $u->id, 'name' => $u->full_name, 'avatar' => $u->avatar_url, 'initials' => $u->initials];

    // Per-category detail line shown under each name in the popup.
    $fmtLate = function ($min) {
        $min = (int) $min;
        if ($min < 60) return $min . ' min late';
        $h = intdiv($min, 60); $m = $min % 60;
        return $m ? "{$h}h {$m}m late" : "{$h}h late";
    };
    $leaveRange = function ($r) {
        $range = $r->start_date->format('d M');
        if ($r->end_date->gt($r->start_date)) $range .= ' – ' . $r->end_date->format('d M');
        return $range . ' · ' . $r->duration_label;      // e.g. "18 Aug – 19 Aug · 2 days"
    };
    $leaveDetailFor = $leaveToday->groupBy('user_id')->map(fn ($rs) => $leaveRange($rs->first()));
    $lateDetailFor = $todayRecs->where('status', 'late')->groupBy('user_id')->map(fn ($rs) => $fmtLate($rs->first()->late_minutes));
    $absentDetailFor = $absentUserIds->mapWithKeys(fn ($id) => [$id => 'Not clocked in today']);

    $remotePeople = \App\Models\User::active()
        ->whereIn('id', $realEmployeeIds->all())
        ->where('attendance_mode', 'remote')
        ->get()
        ->map(fn ($u) => $asPerson($u) + ['detail' => 'Working remotely'])->values()->all();
    $pendingPeople = \App\Models\TimeOffRequest::where('status', 'pending')->with(['employee', 'policy'])->get()
        ->map(fn ($r) => $r->employee ? $asPerson($r->employee) + ['detail' => $leaveRange($r)] : null)
        ->filter()->unique('name')->values()->all();

    $namedIds = collect()->merge($plannedUserIds)->merge($unplannedUserIds)->merge($lateUserIds)->merge($absentUserIds)->unique();
    $peopleMap = \App\Models\User::whereIn('id', $namedIds->all())->get()
        ->mapWithKeys(fn ($u) => [$u->id => $asPerson($u)]);
    $peopleFor = function ($ids, $detailMap = null) use ($peopleMap) {
        return collect($ids)->map(function ($id) use ($peopleMap, $detailMap) {
            $p = $peopleMap[$id] ?? null;
            if (! $p) return null;
            if ($detailMap !== null) $p['detail'] = $detailMap[$id] ?? null;
            return $p;
        })->filter()->values()->all();
    };

    $statCards = [
        ['label' => 'Planned Leaves', 'value' => $plannedLeave, 'sub' => 'On planned leave today', 'icon' => 'palmtree', 'bg' => 'bg-indigo-50 dark:bg-indigo-500/10', 'text' => 'text-indigo-600 dark:text-indigo-400', 'people' => $peopleFor($plannedUserIds, $leaveDetailFor)],
        ['label' => 'Unplanned Leaves', 'value' => $unplannedLeave, 'sub' => 'On unplanned leave today', 'icon' => 'plane-takeoff', 'bg' => 'bg-sky-50 dark:bg-sky-500/10', 'text' => 'text-sky-600 dark:text-sky-400', 'people' => $peopleFor($unplannedUserIds, $leaveDetailFor)],
        ['label' => 'Working Remotely', 'value' => $workingRemotely, 'sub' => 'Remote employees', 'icon' => 'laptop', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10', 'text' => 'text-emerald-600 dark:text-emerald-400', 'people' => $remotePeople],
        ['label' => 'Lateness', 'value' => $lateToday, 'sub' => 'Late arrivals today', 'icon' => 'alarm-clock', 'bg' => 'bg-amber-50 dark:bg-amber-500/10', 'text' => 'text-amber-600 dark:text-amber-400', 'people' => $peopleFor($lateUserIds, $lateDetailFor)],
        ['label' => 'Absences', 'value' => $absentToday, 'sub' => 'Absent today', 'icon' => 'user-x', 'bg' => 'bg-rose-50 dark:bg-rose-500/10', 'text' => 'text-rose-600 dark:text-rose-400', 'people' => $peopleFor($absentUserIds, $absentDetailFor)],
        ['label' => 'Pending Approvals', 'value' => $pendingApprovals, 'sub' => 'Time off queue', 'icon' => 'clock', 'bg' => 'bg-rose-50 dark:bg-rose-500/10', 'text' => 'text-rose-600 dark:text-rose-400', 'action' => $pendingApprovals > 0, 'people' => $pendingPeople],
    ];

    // Live Pending requests
    $pendingRequests = \App\Models\TimeOffRequest::where('status', 'pending')
        ->with(['employee.department', 'policy'])
        ->latest()
        ->take(4)
        ->get();

    // Live Activity Logs or premium fallbacks
    // Build "Recent System Activity" from real records (newest first).
    $activities = collect();

    foreach (\App\Models\User::with('invitedBy')->latest('created_at')->take(6)->get() as $u) {
        $activities->push((object) [
            'action' => 'User Created',
            'description' => (trim($u->first_name . ' ' . $u->last_name) ?: 'An employee') . ' was added to the directory',
            'user' => (object) ['full_name' => optional($u->invitedBy)->full_name ?? 'System'],
            'created_at' => $u->created_at,
        ]);
    }

    foreach (\App\Models\TimeOffRequest::whereIn('status', ['approved', 'rejected'])->with(['employee', 'policy'])->latest('updated_at')->take(6)->get() as $r) {
        $approved = $r->status === 'approved';
        $activities->push((object) [
            'action' => $approved ? 'Leave Approved' : 'Leave Rejected',
            'description' => ($r->policy->name ?? 'Leave') . ' for ' . ($r->employee->full_name ?? 'an employee') . ($approved ? ' was approved' : ' was rejected'),
            'user' => (object) ['full_name' => 'HR'],
            'created_at' => $r->updated_at ?? $r->created_at,
        ]);
    }

    if (class_exists(\App\Models\Event::class)) {
        foreach (\App\Models\Event::with('creator')->latest('created_at')->take(3)->get() as $e) {
            $activities->push((object) [
                'action' => 'Event Added',
                'description' => 'Event “' . $e->title . '” was created',
                'user' => (object) ['full_name' => optional($e->creator)->full_name ?? 'System'],
                'created_at' => $e->created_at,
            ]);
        }
    }

    $activities = $activities->filter(fn ($a) => $a->created_at)->sortByDesc('created_at')->take(6)->values();
@endphp

<div class="space-y-8">

    <!-- Welcome Header & Quick Actions -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Workspace Overview</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Real-time workspace overview, access controls, and pending administrative tasks.</p>
        </div>

        <!-- Quick Actions Bar -->
        <div class="flex flex-wrap items-center gap-3">
            @can('manage-employees')
                <a href="{{ route('employees.create') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    <span>Add Employee</span>
                </a>
            @endcan
            <a href="{{ route('time-off-policies.index') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-brand-600 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-white">
                <i data-lucide="sliders" class="h-4 w-4"></i>
                <span>Manage Policies</span>
            </a>
            <a href="{{ route('time-off.index') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-brand-600 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:hover:text-white">
                <i data-lucide="calendar-check" class="h-4 w-4"></i>
                <span>Review Requests</span>
                @if($pendingApprovals > 0)
                    <span class="ml-1.5 flex h-4.5 w-4.5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white leading-none">
                        {{ $pendingApprovals }}
                    </span>
                @endif
            </a>
        </div>
    </div>

    <!-- Unread-announcement bar + auto-popup -->
    @include('partials.announcement-alert')

    <!-- Attendance Clock Widget (super admins don't clock in) -->
    @unless(auth()->user()->hasRole('super_admin'))
        @include('attendance.partials.clock-widget')
    @endunless

    <!-- Employees waiting for a login code -->
    @include('partials.code-request-hr-banner')

    <!-- Today's snapshot: stat cards (compact — icon left, label above number, avatar stack) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($statCards as $c)
            <div x-data="{ open: false }" class="rounded-2xl bg-white border border-slate-200/80 p-4 sm:p-5 shadow-sm hover:shadow-md transition dark:bg-slate-800 dark:border-slate-800">
                {{-- icon on the left · label above the number --}}
                <div class="flex items-center gap-3">
                    <span class="h-11 w-11 shrink-0 grid place-items-center rounded-xl {{ $c['bg'] }} {{ $c['text'] }}">
                        <i data-lucide="{{ $c['icon'] }}" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 truncate">{{ $c['label'] }}</p>
                        {{-- number with the sub text inline next to it --}}
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 min-w-0">
                            <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white leading-none">{{ $c['value'] }}</h3>
                            @if(!empty($c['action']))
                                <span class="self-center inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 animate-pulse">Action</span>
                            @endif
                            <span class="text-[11px] text-slate-400 truncate">{{ $c['sub'] }}</span>
                        </div>
                    </div>
                </div>

                @if(!empty($c['people']))
                    {{-- overlapping avatars + chevron → opens the popup --}}
                    <button type="button" @click="open = true" class="group mt-3 flex w-full items-center justify-between rounded-lg -mx-1 px-1 py-1 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                        <div class="flex items-center -space-x-2">
                            @foreach(array_slice($c['people'], 0, 4) as $person)
                                @if(!empty($person['avatar']))
                                    <img src="{{ $person['avatar'] }}" alt="{{ $person['name'] }}" title="{{ $person['name'] }}" class="h-7 w-7 rounded-full object-cover ring-2 ring-white dark:ring-slate-800 bg-slate-100">
                                @else
                                    <span title="{{ $person['name'] }}" class="h-7 w-7 grid place-items-center rounded-full ring-2 ring-white dark:ring-slate-800 bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 text-[9px] font-bold text-slate-600 dark:text-slate-200">{{ $person['initials'] }}</span>
                                @endif
                            @endforeach
                            @if(count($c['people']) > 4)
                                <span class="h-7 w-7 grid place-items-center rounded-full ring-2 ring-white dark:ring-slate-800 bg-slate-100 dark:bg-slate-700 text-[10px] font-bold text-slate-500 dark:text-slate-300">+{{ count($c['people']) - 4 }}</span>
                            @endif
                        </div>
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 group-hover:text-slate-500 transition"></i>
                    </button>

                    {{-- Popup: full list with avatars + names --}}
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open = false"></div>
                            <div class="relative w-full max-w-sm max-h-[80vh] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 shadow-xl">
                                <div class="sticky top-0 z-10 flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="h-9 w-9 shrink-0 grid place-items-center rounded-xl {{ $c['bg'] }} {{ $c['text'] }}"><i data-lucide="{{ $c['icon'] }}" class="h-4 w-4"></i></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $c['label'] }}</p>
                                            <p class="text-[11px] text-slate-400">{{ count($c['people']) }} {{ \Illuminate\Support\Str::plural('person', count($c['people'])) }}</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="open = false" class="shrink-0 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="h-5 w-5"></i></button>
                                </div>
                                <div class="p-2">
                                    @foreach($c['people'] as $person)
                                        <a href="{{ route('employees.profile', $person['id']) }}" class="flex items-center gap-3 rounded-xl px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                            @if(!empty($person['avatar']))
                                                <img src="{{ $person['avatar'] }}" alt="{{ $person['name'] }}" class="h-9 w-9 rounded-full object-cover bg-slate-100 shrink-0">
                                            @else
                                                <span class="h-9 w-9 shrink-0 grid place-items-center rounded-full bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 text-[11px] font-bold text-slate-600 dark:text-slate-200">{{ $person['initials'] }}</span>
                                            @endif
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $person['name'] }}</span>
                                                @if(!empty($person['detail']))<span class="block text-[11px] text-slate-400 truncate">{{ $person['detail'] }}</span>@endif
                                            </span>
                                            <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 shrink-0"></i>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </template>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Left column: Approval Queue + Time-off balances + Events + Security notice ·
         Right column: "Your day at a glance" calendar + Recent System Activity. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

    <!-- LEFT COLUMN -->
    <div class="space-y-6">

    <!-- Time Off Approval Queue -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-700">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Time Off Approval Queue</h2>
                <p class="text-xs text-slate-400 mt-0.5">Approve or reject pending time off requests from scoped reporting structures.</p>
            </div>
            <a href="{{ route('time-off.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition dark:text-brand-400">View All</a>
        </div>

        <div class="p-6">
            @if($pendingRequests->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50 dark:text-slate-500">
                        <i data-lucide="check-square" class="h-7 w-7"></i>
                    </div>
                    <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-200">Inbox is empty</h3>
                    <p class="mt-1 text-xs text-slate-400 max-w-xs">There are no pending time-off requests waiting for your approval today.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700/60 -my-4">
                    @foreach($pendingRequests as $req)
                        <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-sm font-bold text-slate-700 dark:from-slate-700 dark:to-slate-600 dark:text-slate-200">
                                    {{ $req->employee->initials ?? 'EM' }}
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-sm font-bold text-slate-950 dark:text-white truncate">{{ $req->employee->full_name ?? 'Unknown' }}</h4>
                                    <p class="text-[11px] text-slate-400">
                                        {{ $req->employee->department->name ?? 'Core' }} &bull; {{ $req->policy->name ?? 'Time Off' }}
                                    </p>
                                    @if($req->reason)
                                        {{-- Reason: truncated inline; hover shows the full text (only when it's actually cut off). --}}
                                        <div class="relative mt-0.5 max-w-xs" x-data="{ show: false }">
                                            <p x-ref="reason{{ $req->id }}"
                                               @mouseenter="show = ($refs.reason{{ $req->id }}.scrollWidth > $refs.reason{{ $req->id }}.clientWidth)"
                                               @mouseleave="show = false"
                                               class="text-[11px] text-slate-600 dark:text-slate-300 italic truncate" style="cursor:help;">
                                                <i data-lucide="message-square-text" class="h-3 w-3 inline -mt-0.5 text-slate-400"></i> {{ $req->reason }}
                                            </p>
                                            <div x-show="show" x-cloak x-transition.opacity
                                                 class="absolute left-0 top-full z-50"
                                                 style="margin-top:.3rem;width:max-content;max-width:20rem;background:#0f172a;color:#fff;font-size:11px;line-height:1.5;padding:.5rem .7rem;border-radius:.5rem;box-shadow:0 10px 28px -10px rgba(0,0,0,.5);white-space:normal;word-break:break-word;">
                                                {{ $req->reason }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pl-13 sm:pl-0 shrink-0">
                                <div class="text-left sm:text-right whitespace-nowrap">
                                    <span class="text-sm font-extrabold text-slate-900 dark:text-white">{{ $req->duration_label }}</span>
                                    <p class="text-[10px] text-slate-400 font-medium">
                                        {{ $req->start_date->format('M d') }}@if($req->start_date->ne($req->end_date)) – {{ $req->end_date->format('M d') }}@endif
                                    </p>
                                </div>

                                <!-- Inline Action Forms -->
                                <div class="flex items-center gap-1.5" x-data="{ openReject: false }">
                                    <!-- Approve Form -->
                                    <form action="{{ route('time-off.approve', $req->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 transition dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20">
                                            Approve
                                        </button>
                                    </form>

                                    <!-- Reject Trigger -->
                                    <button type="button" @click="openReject = true" class="rounded-xl bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-800 transition dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/20">
                                        Reject
                                    </button>

                                    <!-- Rejection Modal -->
                                    <div x-show="openReject" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="openReject = false"></div>
                                        <div class="relative bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700" @click.stop>
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reject Leave Request</h3>
                                            <p class="text-xs text-slate-400 mt-1">Please provide a brief reason for rejecting {{ $req->employee->first_name ?? 'Employee' }}'s request.</p>

                                            <form action="{{ route('time-off.reject', $req->id) }}" method="POST" class="mt-4">
                                                @csrf
                                                <textarea name="rejection_note" required rows="3" placeholder="Rejection notes..." class="w-full text-xs border border-slate-200 bg-slate-50/50 rounded-xl px-3 py-2.5 focus:border-brand-500 focus:outline-none focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"></textarea>

                                                <div class="mt-4 flex justify-end gap-2">
                                                    <button type="button" @click="openReject = false" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                                    <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-rose-700">Confirm Reject</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

        @include('dashboard.partials.timeoff-balances-card')

        {{-- Events summary --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="calendar-days" class="h-4 w-4 text-brand-500"></i> Events</h2>
                <a href="{{ route('events.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">Manage events →</a>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/40">
                    <div class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $eventStats['this_month'] ?? 0 }}</div>
                    <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 mt-0.5">This month</div>
                </div>
                <div class="rounded-xl bg-amber-50 p-4 dark:bg-amber-500/10">
                    <div class="text-2xl font-extrabold text-amber-700 dark:text-amber-400">{{ $eventStats['drafts'] ?? 0 }}</div>
                    <div class="text-[11px] font-semibold text-amber-600 dark:text-amber-400/80 mt-0.5">Drafts (unpublished)</div>
                </div>
            </div>
        </div>

        {{-- Security & Audit Notice --}}
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-800">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Security &amp; Audit Notice</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                This admin console is tracked by the Trickle Hub audit compliance logs. All creations, permission modifications, salary accesses, and manual adjustments of leave balances require a valid justification which is saved recursive-wide on SQLite structures. Refer to the <span class="font-semibold text-brand-600 dark:text-brand-400">IT Compliance Handbook</span> for details.
            </p>
        </div>

    </div>{{-- /LEFT COLUMN --}}

    <!-- RIGHT COLUMN -->
    <div class="space-y-6">
        @include('dashboard.partials.calendar-widget')

        <!-- Recent System Activity (below the calendar, opposite Events) -->
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-700">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Recent System Activity</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Real-time system events log.</p>
                    </div>
                </div>

                <div class="p-6">
                    <ul class="-mb-8">
                        @foreach($activities as $act)
                            <li class="relative pb-8" x-data>
                                <!-- Line separator -->
                                @if(!$loop->last)
                                    <span class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
                                @endif

                                <div class="relative flex items-start space-x-3">
                                    <!-- Dynamic activity icon color -->
                                    @php
                                        $iconColor = match(strtolower($act->action)) {
                                            'user created' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                                            'leave approved' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
                                            'leave rejected' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
                                            'event added' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
                                            'policy updated' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                                            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-450'
                                        };
                                        $icon = match(strtolower($act->action)) {
                                            'user created' => 'user-check',
                                            'leave approved' => 'check-circle-2',
                                            'leave rejected' => 'x-circle',
                                            'event added' => 'calendar-heart',
                                            'policy updated' => 'shield-alert',
                                            default => 'info'
                                        };
                                    @endphp
                                    <div class="relative flex h-10 w-10 items-center justify-center rounded-xl font-bold shadow-sm {{ $iconColor }}">
                                        <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
                                    </div>

                                    <div class="min-w-0 flex-1 py-1.5">
                                        <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                            {{ $act->action }}
                                        </div>
                                        <p class="text-xs text-slate-400 mt-0.5">
                                            {{ $act->description ?? ($act->metadata['notes'] ?? '') }}
                                        </p>
                                        <span class="text-[10px] text-slate-400 mt-1 block">
                                            By {{ $act->user->full_name ?? 'System' }} &bull; {{ $act->created_at instanceof \Carbon\Carbon ? $act->created_at->diffForHumans() : \Carbon\Carbon::parse($act->created_at)->diffForHumans() }}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
    </div>{{-- /RIGHT COLUMN --}}

    </div>{{-- /grid --}}

</div>
@endsection
