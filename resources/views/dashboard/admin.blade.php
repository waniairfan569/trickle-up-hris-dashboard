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

    // Today's snapshot
    $leaveToday = \App\Models\TimeOffRequest::where('status', 'approved')
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

    // Who's in each category (names shown on the cards).
    $plannedUserIds = $leaveToday->reject($isUnplanned)->pluck('user_id')->unique();
    $unplannedUserIds = $leaveToday->filter($isUnplanned)->pluck('user_id')->unique();
    $lateUserIds = $todayRecs->where('status', 'late')->pluck('user_id')->unique();
    $remoteNames = \App\Models\User::active()
        ->whereIn('id', $realEmployeeIds->all())
        ->where('attendance_mode', 'remote')
        ->get(['id', 'first_name', 'last_name'])
        ->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->filter()->values()->all();
    $pendingNames = \App\Models\TimeOffRequest::where('status', 'pending')->with('employee')->get()
        ->map(fn ($r) => optional($r->employee)->full_name)->filter()->unique()->values()->all();

    $namedIds = collect()->merge($plannedUserIds)->merge($unplannedUserIds)->merge($lateUserIds)->merge($absentUserIds)->unique();
    $nameMap = \App\Models\User::whereIn('id', $namedIds->all())->get(['id', 'first_name', 'last_name'])
        ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);
    $namesFor = fn ($ids) => collect($ids)->map(fn ($id) => $nameMap[$id] ?? null)->filter()->values()->all();

    $statCards = [
        ['label' => 'Planned Leaves', 'value' => $plannedLeave, 'sub' => 'On planned leave today', 'icon' => 'palmtree', 'bg' => 'bg-indigo-50 dark:bg-indigo-500/10', 'text' => 'text-indigo-600 dark:text-indigo-400', 'people' => $namesFor($plannedUserIds)],
        ['label' => 'Unplanned Leaves', 'value' => $unplannedLeave, 'sub' => 'On unplanned leave today', 'icon' => 'plane-takeoff', 'bg' => 'bg-sky-50 dark:bg-sky-500/10', 'text' => 'text-sky-600 dark:text-sky-400', 'people' => $namesFor($unplannedUserIds)],
        ['label' => 'Working Remotely', 'value' => $workingRemotely, 'sub' => 'Remote employees', 'icon' => 'laptop', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10', 'text' => 'text-emerald-600 dark:text-emerald-400', 'people' => $remoteNames],
        ['label' => 'Lateness', 'value' => $lateToday, 'sub' => 'Late arrivals today', 'icon' => 'alarm-clock', 'bg' => 'bg-amber-50 dark:bg-amber-500/10', 'text' => 'text-amber-600 dark:text-amber-400', 'people' => $namesFor($lateUserIds)],
        ['label' => 'Absences', 'value' => $absentToday, 'sub' => 'Absent today', 'icon' => 'user-x', 'bg' => 'bg-rose-50 dark:bg-rose-500/10', 'text' => 'text-rose-600 dark:text-rose-400', 'people' => $namesFor($absentUserIds)],
        ['label' => 'Pending Approvals', 'value' => $pendingApprovals, 'sub' => 'Time off queue', 'icon' => 'clock', 'bg' => 'bg-rose-50 dark:bg-rose-500/10', 'text' => 'text-rose-600 dark:text-rose-400', 'action' => $pendingApprovals > 0, 'people' => $pendingNames],
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

    <!-- Attendance Clock Widget -->
    @include('attendance.partials.clock-widget')

    <!-- Employees waiting for a login code -->
    @include('partials.code-request-hr-banner')

    {{-- Left column: Calendar + Approval Queue · Right column: Announcements + Balances.
         Two independent columns so cards stack tightly with no row-alignment gap. --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

    <!-- LEFT COLUMN -->
    <div class="space-y-6">
        @include('dashboard.partials.calendar-widget')

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
                                    <h4 class="text-sm font-bold text-slate-950 dark:text-white">{{ $req->employee->full_name ?? 'Unknown' }}</h4>
                                    <p class="text-[11px] text-slate-400">
                                        {{ $req->employee->department->name ?? 'Core' }} &bull; {{ $req->policy->name ?? 'Time Off' }}
                                    </p>
                                    @if($req->reason)
                                        <p class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 italic max-w-xs truncate" title="{{ $req->reason }}">
                                            <i data-lucide="message-square-text" class="h-3 w-3 inline -mt-0.5 text-slate-400"></i> {{ $req->reason }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pl-13 sm:pl-0">
                                <div class="text-left sm:text-right">
                                    <span class="text-sm font-extrabold text-slate-900 dark:text-white">{{ (float) $req->days_requested }} {{ \Illuminate\Support\Str::plural('day', (float) $req->days_requested) }}</span>
                                    <p class="text-[10px] text-slate-400 font-medium">
                                        {{ $req->start_date->format('M d') }} - {{ $req->end_date->format('M d') }}
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
                                    <button @click="openReject = true" class="rounded-xl bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-850 transition dark:bg-rose-500/10 dark:text-rose-450 dark:hover:bg-rose-500/20">
                                        Reject
                                    </button>

                                    <!-- Rejection Modal (inline overlay modal) -->
                                    <template x-if="openReject">
                                        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                                            <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700" @click.away="openReject = false">
                                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reject Leave Request</h3>
                                                <p class="text-xs text-slate-400 mt-1">Please provide a brief reason for rejecting {{ $req->employee->first_name ?? 'Employee' }}'s request.</p>

                                                <form action="{{ route('time-off.reject', $req->id) }}" method="POST" class="mt-4">
                                                    @csrf
                                                    <textarea name="rejection_note" required rows="3" placeholder="Rejection notes..." class="w-full text-xs border border-slate-200 bg-slate-50/50 rounded-xl p-2.5 focus:border-brand-500 focus:outline-none focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"></textarea>

                                                    <div class="mt-4 flex justify-end gap-2">
                                                        <button type="button" @click="openReject = false" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                                        <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-rose-700">Confirm Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    </div>{{-- /LEFT COLUMN --}}

    <!-- RIGHT COLUMN -->
    <div class="space-y-6">
        @include('partials.announcements')
        @include('dashboard.partials.timeoff-balances-card')
    </div>{{-- /RIGHT COLUMN --}}

    </div>{{-- /grid --}}

    <!-- Today's snapshot: stat cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($statCards as $c)
            <div class="relative rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm hover:-translate-y-1 hover:shadow-md transition duration-200 dark:bg-slate-800 dark:border-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $c['label'] }}</p>
                        <h3 class="mt-2 text-3xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $c['value'] }}</h3>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $c['bg'] }} {{ $c['text'] }} shadow-inner">
                        <i data-lucide="{{ $c['icon'] }}" class="h-6 w-6"></i>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    @if(!empty($c['action']))
                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 animate-pulse">Action Required</span>
                    @endif
                    <span class="text-xs text-slate-400">{{ $c['sub'] }}</span>
                </div>

                @if(!empty($c['people']))
                    <div class="mt-3 flex flex-wrap gap-1" x-data="{ open: false }">
                        @foreach(array_slice($c['people'], 0, 3) as $nm)
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $nm }}</span>
                        @endforeach
                        @if(count($c['people']) > 3)
                            <div class="relative">
                                <button type="button" @click="open = !open" @click.outside="open = false" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">+{{ count($c['people']) - 3 }} more</button>
                                <div x-show="open" x-cloak x-transition class="absolute z-30 bottom-full mb-1 left-0 w-44 rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:bg-slate-800 dark:border-slate-700 max-h-48 overflow-y-auto">
                                    @foreach($c['people'] as $nm)
                                        <p class="px-2 py-1 text-[11px] text-slate-600 dark:text-slate-300 truncate">{{ $nm }}</p>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Recent activity + security notice -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

        <!-- System Activity Feed (2 cols) -->
        <div class="lg:col-span-2">
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
        </div>

        <!-- Security & Audit Notice (1 col) -->
        <div>
            <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Security & Audit Notice</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    This admin console is tracked by the Trickle Hub audit compliance logs. All creations, permission modifications, salary accesses, and manual adjustments of leave balances require a valid justification which is saved recursive-wide on SQLite structures. Refer to the <span class="font-semibold text-brand-600 dark:text-brand-400">IT Compliance Handbook</span> for details.
                </p>
            </div>
        </div>

    </div>

</div>
@endsection
