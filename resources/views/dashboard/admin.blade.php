@extends('layouts.hr-app')

@section('title', 'Admin Dashboard')
@section('breadcrumb', 'Admin Panel')

@section('content')
@php
    // Robust, dynamic metrics directly from the DB
    // "Total Directory" mirrors the /employees directory, which lists employee records.
    $totalEmployees = \App\Models\Employee::count();
    
    $onLeaveToday = \App\Models\TimeOffRequest::where('status', 'approved')
        ->whereDate('start_date', '<=', today())
        ->whereDate('end_date', '>=', today())
        ->count();
        
    $pendingApprovals = \App\Models\TimeOffRequest::where('status', 'pending')->count();
    
    // Active onboarding: Count employees hired in last 90 days as mock onboarding
    $activeOnboarding = \App\Models\User::whereNotNull('hire_date')
        ->whereDate('hire_date', '>=', today()->subDays(90))
        ->whereDate('hire_date', '<=', today())
        ->count();
    
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

    <!-- Who's on leave today -->
    @include('partials.on-leave-today', ['people' => $onLeavePeople])

    <!-- Calendar + Time-off balances (shared across dashboards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @include('dashboard.partials.calendar-widget')
        @include('dashboard.partials.timeoff-balances')
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
        
        <!-- Total Employees -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm hover:-translate-y-1 hover:shadow-md transition duration-200 dark:bg-slate-800 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Directory</p>
                    <h3 class="mt-2 text-3xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $totalEmployees }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 shadow-inner">
                    <i data-lucide="users" class="h-6 w-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <i data-lucide="trending-up" class="mr-1 h-3.5 w-3.5"></i>
                    <span>Stable</span>
                </span>
                <span class="text-xs text-slate-400">Active records</span>
            </div>
        </div>

        <!-- On Leave Today -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm hover:-translate-y-1 hover:shadow-md transition duration-200 dark:bg-slate-800 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">On Leave Today</p>
                    <h3 class="mt-2 text-3xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $onLeaveToday }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 shadow-inner">
                    <i data-lucide="plane-takeoff" class="h-6 w-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span class="text-xs text-slate-500 dark:text-slate-400">Approved time off slots</span>
            </div>
        </div>

        <!-- Pending Approvals -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm hover:-translate-y-1 hover:shadow-md transition duration-200 dark:bg-slate-800 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Approvals</p>
                    <h3 class="mt-2 text-3xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $pendingApprovals }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 shadow-inner">
                    <i data-lucide="clock" class="h-6 w-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                @if($pendingApprovals > 0)
                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 animate-pulse">
                        <span>Action Required</span>
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <span>Cleared</span>
                    </span>
                @endif
                <span class="text-xs text-slate-400">Time off queue</span>
            </div>
        </div>

        <!-- Active Onboardings -->
        <div class="relative overflow-hidden rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm hover:-translate-y-1 hover:shadow-md transition duration-200 dark:bg-slate-800 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">New Hires (90d)</p>
                    <h3 class="mt-2 text-3xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $activeOnboarding }}</h3>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 shadow-inner">
                    <i data-lucide="rocket" class="h-6 w-6"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span class="text-xs text-slate-500 dark:text-slate-400">Active onboarding setups</span>
            </div>
        </div>

    </div>

    <!-- Main Grid Dashboard Content -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        
        <!-- Left 2 Columns: Leave requests approvals queue -->
        <div class="lg:col-span-2 space-y-6">
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
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-950 dark:text-white">{{ $req->employee->full_name ?? 'Unknown' }}</h4>
                                            <p class="text-[11px] text-slate-400">
                                                {{ $req->employee->department->name ?? 'Core' }} &bull; {{ $req->policy->name ?? 'Time Off' }}
                                            </p>
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
            
            <!-- Quick System Audit Info -->
            <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Security & Audit Notice</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    This admin console is tracked by the Workable HRIS audit compliance logs. All creations, permission modifications, salary accesses, and manual adjustments of leave balances require a valid justification which is saved recursive-wide on SQLite structures. Refer to the <span class="font-semibold text-brand-600 dark:text-brand-400">IT Compliance Handbook</span> for details.
                </p>
            </div>
        </div>

        <!-- Right 1 Column: System Activity Feed -->
        <div class="space-y-6">
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

    </div>

</div>
@endsection
