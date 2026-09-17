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

    // Overtime requests are submissions to the designated overtime form; "awaiting" mirrors the Form Responses inbox.
    $overtimeForm = plan_allows('forms') ? \App\Models\CompanyForm::overtimeForm() : null;
    $pendingOvertime = $overtimeForm
        ? \App\Models\FormSubmission::where('form_id', $overtimeForm->id)->where('status', 'submitted')
            ->where(fn ($w) => $w->whereNull('review_status')->orWhere('review_status', 'pending'))->count()
        : 0;

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

    // Working remotely TODAY: base-remote, an approved WFH request today, a hybrid
    // remote weekday, or a company-wide WFH day (mirrors User::effectiveAttendanceMode).
    $companyRemoteToday = \App\Models\CompanyWfhDay::isCompanyRemote(today());
    $wfhTodayIds = \App\Models\TimeOffRequest::where('status', 'approved')
        ->whereDate('start_date', '<=', today()->toDateString())
        ->whereDate('end_date', '>=', today()->toDateString())
        ->whereHas('policy', fn ($q) => $q->workFromHome())
        ->pluck('user_id')->unique();
    $weekdayShort = today()->format('D');
    $remoteEmployees = \App\Models\User::active()
        ->whereIn('id', $realEmployeeIds->all())
        ->get()
        ->filter(fn ($u) => $companyRemoteToday
            || $wfhTodayIds->contains($u->id)
            || in_array($weekdayShort, (array) ($u->remote_days ?? []), true)
            || ($u->attendance_mode ?? 'biometric') === 'remote');
    $workingRemotely = $remoteEmployees->count();

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

    $remotePeople = $remoteEmployees
        ->map(fn ($u) => $asPerson($u) + ['detail' => $wfhTodayIds->contains($u->id) ? 'Working from home today' : 'Working remotely'])
        ->values()->all();
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

    // Live Pending requests (Work From Home is handled in its own queue below).
    $pendingRequests = \App\Models\TimeOffRequest::where('status', 'pending')
        ->excludingWorkFromHome()
        ->with(['employee.department', 'policy'])
        ->latest()
        ->take(4)
        ->get();

    // Pending Work From Home requests — WFH is a time-off policy, surfaced in its
    // own admin queue so it can be approved/rejected separately.
    $pendingWfhCount = \App\Models\TimeOffRequest::where('status', 'pending')
        ->whereHas('policy', fn ($q) => $q->workFromHome())->count();
    $pendingWfhRequests = \App\Models\TimeOffRequest::where('status', 'pending')
        ->whereHas('policy', fn ($q) => $q->workFromHome())
        ->with(['employee.department', 'policy'])
        ->latest()
        ->take(4)
        ->get();
@endphp

<div class="space-y-8">

    <!-- Welcome Header -->
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Workspace Overview</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Real-time workspace overview, access controls, and pending administrative tasks.</p>
    </div>

    <!-- New-workspace setup checklist (admins, until complete/dismissed) -->
    @include('dashboard.partials.getting-started-card')

    <!-- Unread-announcement bar + auto-popup -->
    @include('partials.announcement-alert')

    @unless(auth()->user()->hasRole('super_admin'))
    @endunless

    <!-- Employees waiting for a login code -->
    @include('partials.code-request-hr-banner')

    <!-- Today's snapshot: stat cards (compact — icon left, label above number, avatar stack) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        @foreach($statCards as $c)
            <div x-data="{ open: false }" class="flex flex-col rounded-2xl bg-white border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition dark:bg-slate-800 dark:border-slate-800">
                {{-- icon on top · label (fixed height so numbers align) · number · sub --}}
                <span class="h-10 w-10 grid place-items-center rounded-xl {{ $c['bg'] }} {{ $c['text'] }}">
                    <i data-lucide="{{ $c['icon'] }}" class="h-5 w-5"></i>
                </span>
                <p class="mt-3 min-h-[2rem] text-[11px] font-bold uppercase tracking-wider leading-tight text-slate-400">{{ $c['label'] }}</p>
                <div class="mt-1 flex items-center gap-2">
                    <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white leading-none">{{ $c['value'] }}</h3>
                    @if(!empty($c['action']))
                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 animate-pulse">Action</span>
                    @endif
                </div>
                <p class="mt-1 text-[11px] text-slate-400 leading-snug">{{ $c['sub'] }}</p>

                @if(!empty($c['people']))
                    {{-- overlapping avatars + chevron → opens the popup (pinned to the bottom so rows align) --}}
                    <button type="button" @click="open = true" class="group mt-auto flex w-full items-center justify-between rounded-lg -mx-1 px-1 pt-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
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

    {{-- Approval queues — one row of three equal-height columns --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Time Off Approval Queue -->
    <div class="flex flex-col rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <div class="flex items-center gap-3 border-b border-slate-100 p-6 dark:border-slate-700">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400"><i data-lucide="calendar-check" class="h-5 w-5"></i></span>
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Time Off Approval Queue</h2>
                <p class="text-xs text-slate-400 mt-0.5">Approve or reject pending time off requests.</p>
            </div>
        </div>

        <div class="p-6 flex-1">
            @if($pendingRequests->isEmpty())
                <div class="flex h-full flex-col items-center justify-center py-6 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50 dark:text-slate-500">
                        <i data-lucide="check-square" class="h-7 w-7"></i>
                    </div>
                    <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-200">Inbox is empty</h3>
                    <p class="mt-1 text-xs text-slate-400 max-w-xs">No pending time-off requests right now.</p>
                </div>
            @else
                <div class="divide-y divide-slate-100 dark:divide-slate-700/60 -my-4">
                    @foreach($pendingRequests as $req)
                        <div class="py-4 space-y-3">
                            <div class="flex items-center space-x-3 min-w-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-sm font-bold text-slate-700 dark:from-slate-700 dark:to-slate-600 dark:text-slate-200">
                                    {{ $req->employee->initials ?? 'EM' }}
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-sm font-bold text-slate-950 dark:text-white truncate">{{ $req->employee->full_name ?? 'Unknown' }}</h4>
                                    @if($req->reason)
                                        {{-- Reason: truncated inline; hover shows the full text (only when it's actually cut off). --}}
                                        <div class="relative mt-0.5 max-w-full" x-data="{ show: false }">
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

                            <div class="flex items-center justify-between gap-3 pl-13">
                                <div class="text-left whitespace-nowrap">
                                    <span class="text-sm font-extrabold text-slate-900 dark:text-white">{{ $req->duration_label }}</span>
                                    <p class="text-[10px] text-slate-400 font-medium">
                                        {{ $req->start_date->format('M d') }}@if($req->start_date->ne($req->end_date)) – {{ $req->end_date->format('M d') }}@endif
                                    </p>
                                    <p class="text-[10px] text-slate-400" title="Applied {{ $req->created_at->format('D, d M Y · g:i A') }}">
                                        <i data-lucide="clock" class="h-2.5 w-2.5 inline -mt-0.5"></i> Applied {{ $req->created_at->format('d M') }} · {{ $req->created_at->diffForHumans(null, true) }} ago
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
        <div class="mt-auto border-t border-slate-100 dark:border-slate-700 px-6 py-3">
            <a href="{{ route('time-off.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition dark:text-brand-400">View All →</a>
        </div>
    </div>

        <!-- Overtime Requests -->
        @if(plan_allows('forms'))
        <div class="flex flex-col rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
            <div class="flex items-center gap-3 border-b border-slate-100 p-6 dark:border-slate-700">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400"><i data-lucide="alarm-clock" class="h-5 w-5"></i></span>
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">Overtime Requests
                        @if($pendingOvertime > 0)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-[11px] font-bold text-white">{{ $pendingOvertime }}</span>@endif
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Overtime submitted for approval.</p>
                </div>
            </div>
            <div class="p-6 flex-1 flex flex-col justify-center">
                @if($pendingOvertime > 0)
                    <a href="{{ route('company-forms.inbox') }}" class="flex items-center justify-between rounded-xl bg-violet-50 px-4 py-3.5 hover:bg-violet-100 transition dark:bg-violet-500/10 dark:hover:bg-violet-500/20">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200"><span class="font-extrabold text-violet-700 dark:text-violet-300">{{ $pendingOvertime }}</span> {{ \Illuminate\Support\Str::plural('request', $pendingOvertime) }} awaiting review</span>
                        <span class="inline-flex items-center gap-1 text-sm font-bold text-violet-700 dark:text-violet-300">Review <i data-lucide="arrow-right" class="h-4 w-4"></i></span>
                    </a>
                @else
                    <div class="flex flex-col items-center justify-center py-6 text-center">
                        <div class="grid h-14 w-14 place-items-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50 dark:text-slate-500"><i data-lucide="alarm-clock" class="h-7 w-7"></i></div>
                        <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-200">No overtime requests</h3>
                        <p class="mt-1 text-xs text-slate-400 max-w-xs">No overtime submissions to review.</p>
                    </div>
                @endif
            </div>
            <div class="mt-auto border-t border-slate-100 dark:border-slate-700 px-6 py-3">
                <a href="{{ route('company-forms.inbox') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition dark:text-brand-400">View All →</a>
            </div>
        </div>
        @endif

        <!-- Work From Home Requests -->
        <div class="flex flex-col rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
            <div class="flex items-center gap-3 border-b border-slate-100 p-6 dark:border-slate-700">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400"><i data-lucide="house" class="h-5 w-5"></i></span>
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900 dark:text-white">WFH Requests
                        @if($pendingWfhCount > 0)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-rose-500 px-1.5 text-[11px] font-bold text-white">{{ $pendingWfhCount }}</span>@endif
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Approve or reject remote-work requests.</p>
                </div>
            </div>

            <div class="p-6 flex-1">
                @if($pendingWfhRequests->isEmpty())
                    <div class="flex h-full flex-col items-center justify-center py-6 text-center">
                        <div class="grid h-14 w-14 place-items-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50 dark:text-slate-500"><i data-lucide="house" class="h-7 w-7"></i></div>
                        <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-200">No WFH requests</h3>
                        <p class="mt-1 text-xs text-slate-400 max-w-xs">No work-from-home requests to review.</p>
                    </div>
                @else
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 -my-4">
                        @foreach($pendingWfhRequests as $req)
                            <div class="py-4 flex items-center justify-between gap-3">
                                <div class="flex items-center space-x-3 min-w-0">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-sm font-bold text-slate-700 dark:from-slate-700 dark:to-slate-600 dark:text-slate-200">
                                        {{ $req->employee->initials ?? 'EM' }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-bold text-slate-950 dark:text-white truncate">{{ $req->employee->full_name ?? 'Unknown' }}</h4>
                                        <p class="text-[11px] text-slate-400 truncate">
                                            {{ $req->start_date->format('M d') }}@if($req->start_date->ne($req->end_date)) – {{ $req->end_date->format('M d') }}@endif · {{ $req->duration_label }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0" x-data="{ openReject: false }">
                                    <form action="{{ route('time-off.approve', $req->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 transition dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20">Approve</button>
                                    </form>
                                    <button type="button" @click="openReject = true" class="rounded-xl bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-800 transition dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/20">Reject</button>

                                    <div x-show="openReject" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="openReject = false"></div>
                                        <div class="relative bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700" @click.stop>
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reject WFH Request</h3>
                                            <p class="text-xs text-slate-400 mt-1">Please provide a brief reason for rejecting {{ $req->employee->first_name ?? 'this' }}'s work-from-home request.</p>
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
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="mt-auto border-t border-slate-100 dark:border-slate-700 px-6 py-3">
                <a href="{{ route('time-off.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition dark:text-brand-400">View All →</a>
            </div>
        </div>

    </div>{{-- /approval queues row --}}

    {{-- Day at a glance (matches the employee dashboard) --}}
    @include('dashboard.partials.day-at-a-glance')

</div>
@endsection
