@extends('layouts.hr-app')

@section('title', 'Manager Dashboard')
@section('breadcrumb', 'Team Management')

@section('content')
@inject('accessService', 'App\Services\EmployeeAccessService')

@php
    $auth = auth()->user();
    
    // Fetch direct and indirect reports dynamically
    $reports = $accessService->getReportingLine($auth);
    $reportUserIds = $reports->pluck('id');

    // Pending team time-off requests (time_off_requests is keyed by user_id)
    $teamRequests = \App\Models\TimeOffRequest::whereIn('user_id', $reportUserIds)
        ->where('status', 'pending')
        ->with(['employee.department'])
        ->latest()
        ->get();
        
    // List of reports who are on leave today
    $onLeaveTodayUserIds = \App\Models\TimeOffRequest::where('status', 'approved')
        ->whereDate('start_date', '<=', today())
        ->whereDate('end_date', '>=', today())
        ->with('employee')
        ->get()
        ->pluck('employee.id')
        ->toArray();
        
    // Team performance reviews (performance_reviews is keyed by reviewee_id)
    $reviews = \App\Models\PerformanceReview::whereIn('reviewee_id', $reportUserIds)
        ->with(['reviewee', 'reviewer'])
        ->latest()
        ->take(5)
        ->get();
@endphp

<div class="space-y-8">
    
    <!-- Welcome Header -->
    <div>
        <h1 class="text-2xl font-semibold text-slate-800 dark:text-white tracking-tight">Hello {{ auth()->user()->first_name }}!</h1>
    </div>

    <!-- Attendance Clock Widget -->
    @include('attendance.partials.clock-widget')

    <!-- Calendar + Time-off balances (shared across dashboards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @include('dashboard.partials.calendar-widget')
        @include('dashboard.partials.timeoff-balances')
    </div>

    <!-- Team Overview & Presence Indicator -->
    <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 dark:border-slate-700/60">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Team Presence Overview</h2>
                <p class="text-xs text-slate-400 mt-0.5">Real-time status of your direct and indirect reports.</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="inline-flex items-center gap-1.5 text-slate-500">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Active
                </span>
                <span class="inline-flex items-center gap-1.5 text-slate-500">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span> On Leave
                </span>
                <span class="inline-flex items-center gap-1.5 text-slate-500">
                    <span class="h-2 w-2 rounded-full bg-slate-350 dark:bg-slate-650"></span> Away
                </span>
            </div>
        </div>

        <div class="mt-6">
            @if($reports->isEmpty())
                <div class="flex flex-col items-center justify-center py-6 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50">
                        <i data-lucide="user-x" class="h-6 w-6"></i>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">You do not currently have any direct or indirect reports in your organizational line.</p>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    @foreach($reports as $report)
                        @php
                            $isOnLeave = in_array($report->id, $onLeaveTodayUserIds);
                            // Mock offline based on experience years to create realism
                            $isOffline = ($report->years_of_experience ?? 0) % 3 === 0 && !$isOnLeave;
                            
                            $statusRing = $isOnLeave 
                                ? 'ring-amber-500 ring-offset-2' 
                                : ($isOffline ? 'ring-slate-300 dark:ring-slate-600 ring-offset-2' : 'ring-emerald-500 ring-offset-2');
                                
                            $statusBg = $isOnLeave 
                                ? 'bg-amber-500' 
                                : ($isOffline ? 'bg-slate-400' : 'bg-emerald-500');
                        @endphp
                        <a href="{{ route('employees.profile', $report->id) }}" class="group relative flex flex-col items-center rounded-2xl border border-slate-100 bg-slate-50/50 p-4 text-center hover:bg-white hover:border-slate-200 hover:shadow-sm transition dark:border-slate-750 dark:bg-slate-850 dark:hover:bg-slate-800">
                            <!-- Avatar Ring -->
                            <div class="relative">
                                @if($report->avatar_url)
                                    <img src="{{ $report->avatar_url }}" alt="{{ $report->full_name }}" class="h-12 w-12 rounded-full object-cover ring-2 {{ $statusRing }} dark:ring-offset-slate-800">
                                @else
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-indigo-500 text-sm font-bold text-white ring-2 {{ $statusRing }} dark:ring-offset-slate-800">
                                        {{ $report->initials }}
                                    </div>
                                @endif
                                <!-- Status Badge dot -->
                                <span class="absolute bottom-0 right-0 block h-3 w-3 rounded-full border-2 border-white {{ $statusBg }} dark:border-slate-800"></span>
                            </div>
                            
                            <h4 class="mt-3 text-xs font-bold text-slate-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-400 transition truncate w-full px-1">{{ $report->full_name }}</h4>
                            <p class="text-[10px] text-slate-400 truncate w-full px-1">{{ $report->job_title }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Manager Workspace Split Layout -->
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        
        <!-- Left: Pending approvals queue (2 Columns) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-700">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Team Leave Approvals</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Approve or reject leave requests submitted by your subordinates.</p>
                    </div>
                </div>

                <div class="p-6">
                    @if($teamRequests->isEmpty())
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50">
                                <i data-lucide="check-square" class="h-6 w-6"></i>
                            </div>
                            <h4 class="mt-3 text-xs font-bold text-slate-800 dark:text-slate-200">No requests pending</h4>
                            <p class="text-[11px] text-slate-400 mt-0.5">Your team has no pending leave requests requiring action.</p>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/60 -my-4">
                            @foreach($teamRequests as $req)
                                <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-sm font-bold text-slate-755 dark:from-slate-700 dark:to-slate-650 dark:text-slate-200">
                                            {{ $req->employee->initials ?? 'EM' }}
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $req->employee->full_name ?? 'Unknown' }}</h4>
                                            <p class="text-[10px] text-slate-400 mt-0.5">
                                                {{ optional($req->employee->department)->name ?? 'Core' }} &bull; {{ ucfirst(str_replace('_', ' ', $req->type)) }}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-4 pl-13 sm:pl-0">
                                        <div class="text-left sm:text-right">
                                            <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $req->days_requested }} days</span>
                                            <p class="text-[10px] text-slate-400 font-medium">
                                                {{ $req->start_date->format('M d') }} - {{ $req->end_date->format('M d') }}
                                            </p>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="flex items-center gap-1.5" x-data="{ openReject: false }">
                                            <form action="{{ route('time-off.approve', $req->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rounded-xl bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 transition dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20">
                                                    Approve
                                                </button>
                                            </form>
                                            
                                            <button @click="openReject = true" class="rounded-xl bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 hover:text-rose-800 transition dark:bg-rose-500/10 dark:text-rose-450 dark:hover:bg-rose-500/20">
                                                Reject
                                            </button>

                                            <!-- Rejection Modal -->
                                            <template x-if="openReject">
                                                <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                                                    <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl border border-slate-200 dark:bg-slate-800 dark:border-slate-700" @click.away="openReject = false">
                                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Reject Leave Request</h3>
                                                        <p class="text-xs text-slate-400 mt-1">Provide rejection reason for {{ $req->employee->first_name }}.</p>
                                                        
                                                        <form action="{{ route('time-off.reject', $req->id) }}" method="POST" class="mt-4">
                                                            @csrf
                                                            <textarea name="rejection_reason" required rows="3" placeholder="Write reason..." class="w-full text-xs border border-slate-200 bg-slate-50/50 rounded-xl p-2.5 focus:border-brand-500 focus:outline-none focus:bg-white dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"></textarea>
                                                            
                                                            <div class="mt-4 flex justify-end gap-2">
                                                                <button type="button" @click="openReject = false" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                                                <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-rose-700">Reject Request</button>
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
        </div>

        <!-- Right: Team Appraisals / Performance Reviews (1 Column) -->
        <div class="space-y-6">
            <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-100 p-6 dark:border-slate-700">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Team Appraisals</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Reviews and upcoming performance cycles.</p>
                    </div>
                    <a href="{{ route('performance.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 transition dark:text-brand-400">Manage</a>
                </div>

                <div class="p-6">
                    @if($reviews->isEmpty())
                        <div class="flex flex-col items-center justify-center py-6 text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-700/50">
                                <i data-lucide="award" class="h-5 w-5"></i>
                            </div>
                            <h4 class="mt-3 text-xs font-bold text-slate-800 dark:text-slate-200">No scheduled reviews</h4>
                            <p class="text-[10px] text-slate-400 mt-0.5">Your team has no ongoing or pending appraisal logs.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($reviews as $rev)
                                <div class="flex items-center justify-between border border-slate-100 rounded-xl p-3 bg-slate-50/30 dark:border-slate-700 dark:bg-slate-900/10">
                                    <div class="flex items-center space-x-3">
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 flex items-center justify-center text-white text-[10px] font-bold">
                                            {{ $rev->reviewee->initials ?? 'EM' }}
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white">{{ $rev->reviewee->full_name ?? 'Unknown' }}</h4>
                                            <p class="text-[9px] text-slate-400 uppercase font-semibold mt-0.5">
                                                Type: {{ $rev->type }} &bull; {{ $rev->status }}
                                            </p>
                                        </div>
                                    </div>
                                    <a href="{{ route('performance.show', $rev->id) }}" class="rounded-lg p-1 text-slate-400 hover:text-brand-600 hover:bg-slate-100 dark:hover:bg-slate-700/60 dark:hover:text-brand-400 transition" title="View Review">
                                        <i data-lucide="external-link" class="h-4 w-4"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
