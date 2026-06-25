@extends('layouts.hr-app')

@section('title', $employee->full_name)
@section('breadcrumb', 'Employee Profile')

@section('content')
@php
    $auth = auth()->user();
    
    // Retrieve associated Employee
    $empRecord = \App\Models\Employee::where('user_id', $employee->id)->first();
    
    // Retrieve TimeOffBalances from modern policy-driven DB schema
    $balances = \App\Models\TimeOffBalance::where('user_id', $employee->id)
        ->where('year', date('Y'))
        ->with('policy')
        ->get();
        
    $annualBalance = $balances->first(fn($b) => optional($b->policy)->type === 'annual');
    $sickBalance = $balances->first(fn($b) => optional($b->policy)->type === 'sick');

    $annualTotal = $annualBalance ? (float)$annualBalance->balance : 20.0;
    $annualUsed = $annualBalance ? (float)$annualBalance->used : 0.0;
    $annualPending = $annualBalance ? (float)$annualBalance->pending : 0.0;
    $annualRemaining = max(0.0, $annualTotal - $annualUsed - $annualPending);

    $sickTotal = $sickBalance ? (float)$sickBalance->balance : 10.0;
    $sickUsed = $sickBalance ? (float)$sickBalance->used : 0.0;
    $sickRemaining = max(0.0, $sickTotal - $sickUsed);
        
    $requests = \App\Models\TimeOffRequest::where('user_id', $employee->id)
        ->with('policy')
        ->latest()
        ->take(6)
        ->get();
        
    $reviews = \App\Models\PerformanceReview::where('reviewee_id', $employee->id)
        ->with('reviewer')
        ->latest()
        ->get();
        
    // Standard mock documents
    $documents = [
        ['name' => 'Signed Employment Contract.pdf', 'status' => 'Signed', 'date' => 'Mar 15, 2026', 'size' => '1.2 MB'],
        ['name' => 'W-4 Tax Form 2026.pdf', 'status' => 'Uploaded', 'date' => 'Mar 16, 2026', 'size' => '840 KB'],
        ['name' => 'Direct Deposit Authorization.pdf', 'status' => 'Pending Signature', 'date' => 'Pending', 'size' => '320 KB']
    ];
@endphp

<div class="space-y-8" x-data="{ activeTab: new URLSearchParams(window.location.search).get('tab') || 'personal' }">
    
    <!-- Header Hero Banner -->
    <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <!-- Banner Gradient -->
        <div class="h-32 bg-gradient-to-r from-brand-500 to-indigo-600"></div>
        
        <!-- Profile Picture & Brief -->
        <div class="px-6 pb-6 relative flex flex-col items-center text-center sm:flex-row sm:items-end sm:text-left sm:gap-6 -mt-10">
            <div class="relative">
                @if($employee->avatar_url)
                    <img src="{{ $employee->avatar_url }}" alt="{{ $employee->full_name }}" class="h-24 w-24 rounded-2xl object-cover border-4 border-white bg-slate-100 ring-1 ring-slate-200/50 shadow-md dark:border-slate-800">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-400 to-indigo-500 text-3xl font-bold text-white border-4 border-white shadow-md dark:border-slate-800 dark:ring-offset-slate-800">
                        {{ $employee->initials }}
                    </div>
                @endif
            </div>

            <div class="mt-4 flex-1 sm:mt-0 space-y-1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight dark:text-white">{{ $employee->full_name }}</h1>
                    <div class="flex items-center gap-1.5 justify-center">
                        <x-role-badge :role="$employee->role" />
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 capitalize dark:bg-emerald-500/10 dark:text-emerald-400">
                            {{ $fields['employee_status'] ?? 'Active' }}
                        </span>
                    </div>
                </div>
                <p class="text-xs font-semibold text-brand-600 dark:text-brand-400">{{ $fields['job_title'] ?? 'Employee' }} &bull; {{ $employee->department->name ?? 'Core' }}</p>
                <p class="text-[10px] text-slate-400 font-medium">Joined: {{ isset($fields['joined_at']) ? \Carbon\Carbon::parse($fields['joined_at'])->format('M d, Y') : 'Not specified' }}</p>
            </div>

            <!-- Profile Action Buttons -->
            @if($auth->canEdit($employee))
                <div class="mt-4 flex gap-2 sm:mt-0">
                    <a href="{{ route('employees.edit', $employee->id) }}" class="inline-flex items-center gap-x-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                        <i data-lucide="edit-3" class="h-4 w-4"></i>
                        <span>Edit Profile</span>
                    </a>
                </div>
            @endif
        </div>

        <!-- Alpine Tab List -->
        <div class="flex border-t border-slate-100 px-6 dark:border-slate-700/60 overflow-x-auto select-none">
            <a href="{{ route('employees.profile', $employee->id) }}" class="whitespace-nowrap border-b-2 border-transparent py-4 px-4 text-xs font-bold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition">Dynamic Profile</a>
            <button @click="activeTab = 'personal'" :class="activeTab === 'personal' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'" class="whitespace-nowrap border-b-2 py-4 px-4 text-xs font-bold transition focus:outline-none">Personal Info</button>
            <button @click="activeTab = 'work'" :class="activeTab === 'work' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'" class="whitespace-nowrap border-b-2 py-4 px-4 text-xs font-bold transition focus:outline-none">Work Parameters</button>
            <button @click="activeTab = 'timeoff'" :class="activeTab === 'timeoff' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'" class="whitespace-nowrap border-b-2 py-4 px-4 text-xs font-bold transition focus:outline-none">Time Off Logs</button>
            <button @click="activeTab = 'performance'" :class="activeTab === 'performance' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'" class="whitespace-nowrap border-b-2 py-4 px-4 text-xs font-bold transition focus:outline-none">Performance</button>
            <button @click="activeTab = 'documents'" :class="activeTab === 'documents' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-650'" class="whitespace-nowrap border-b-2 py-4 px-4 text-xs font-bold transition focus:outline-none">Paperwork Documents</button>
        </div>
    </div>

    <!-- Active Tab Details Container -->
    <div class="space-y-6">
        
        <!-- 1. PERSONAL INFO TAB -->
        <div x-show="activeTab === 'personal'" x-cloak class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Basic Details -->
            <div class="md:col-span-2 rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Contact & Residential Details</h3>
                
                @if(isset($fields['email']))
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Primary Email</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">{{ $fields['email'] }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Phone Number</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">{{ $fields['phone'] ?? 'N/A' }}</span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Home Address</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">
                                {{ $fields['address'] ?? 'Not registered' }}
                                @if(isset($fields['city'])) , {{ $fields['city'] }} @endif
                                @if(isset($fields['country'])) , {{ $fields['country'] }} @endif
                            </span>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-6 text-center">
                        <i data-lucide="lock" class="h-8 w-8 text-slate-400"></i>
                        <h4 class="mt-3 text-xs font-bold text-slate-800 dark:text-slate-200">Restricted Profile Access</h4>
                        <p class="text-[10px] text-slate-400 mt-0.5">Contact parameters are only visible to the profile owner and Super/HR Admins.</p>
                    </div>
                @endif
            </div>

            <!-- Profile Sidebar metadata -->
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">HR Context</h3>
                
                <div class="space-y-4 text-xs">
                    <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="font-semibold text-slate-400">System ID</span>
                        <span class="font-bold text-slate-850 dark:text-white">EMP-{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1.5 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="font-semibold text-slate-400">Timezone</span>
                        <span class="font-bold text-slate-850 dark:text-white">{{ $employee->timezone ?? 'UTC' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-1.5">
                        <span class="font-semibold text-slate-400">Status</span>
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 uppercase">
                            {{ $employee->status ?? 'active' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. WORK PARAMETERS TAB -->
        <div x-show="activeTab === 'work'" x-cloak class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Job Details -->
            <div class="md:col-span-2 rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Employment & Job Information</h3>
                
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Job Title</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">{{ $fields['job_title'] ?? 'Employee' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Department Assignment</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">{{ $employee->department->name ?? 'Unassigned' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Direct Line Manager</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block">
                            @if($employee->manager)
                                <a href="{{ route('employees.profile', $employee->manager->id) }}" class="text-brand-600 hover:text-brand-700 transition dark:text-brand-400">
                                    {{ $employee->manager->full_name }}
                                </a>
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Employment Type</span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200 mt-1 block capitalize">{{ str_replace('_', ' ', $empRecord->employment_type ?? 'Full Time') }}</span>
                    </div>
                </div>
            </div>

            <!-- Gated Compensation widget -->
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-6">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="shield-check" class="h-4 w-4 text-indigo-500"></i>
                    <span>Compensation</span>
                </h3>
                
                @if(isset($fields['salary']))
                    <div class="space-y-4">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Annual Base Salary</span>
                            <span class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight mt-1.5 block">
                                ${{ number_format($fields['salary'], 2) }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 leading-normal">This salary figure is securely locked behind Super/HR Admin authorization policies.</p>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-4 text-center">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-700/50">
                            <i data-lucide="eye-off" class="h-5 w-5"></i>
                        </div>
                        <h4 class="mt-3 text-xs font-bold text-slate-800 dark:text-slate-200">Restricted Salary Data</h4>
                        <p class="text-[10px] text-slate-400 mt-0.5">Base compensation is only visible to authorized administrators.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- 3. TIME OFF LOGS TAB -->
        <div x-show="activeTab === 'timeoff'" x-cloak class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Left: Scoped Balance Widgets -->
            <div class="md:col-span-2 space-y-6">
                <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-6">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Leave Allowances Summary</h3>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Annual Leave allowance -->
                        <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/20 dark:border-slate-700/60 dark:bg-slate-900/10">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Annual leave remaining</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $annualRemaining }}</span>
                                <span class="text-xs text-slate-400">/ {{ $annualTotal }} days</span>
                            </div>
                        </div>

                        <!-- Sick Leave allowance -->
                        <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/20 dark:border-slate-700/60 dark:bg-slate-900/10">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Sick leave remaining</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $sickRemaining }}</span>
                                <span class="text-xs text-slate-400">/ {{ $sickTotal }} days</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leave Request History -->
                <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
                    <div class="border-b border-slate-100 p-6 dark:border-slate-700">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Leave History</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Timeline of recent leave submissions and statuses.</p>
                    </div>
                    
                    <div class="p-6">
                        @if($requests->isEmpty())
                            <p class="text-xs text-slate-400">No leave requests have been logged in the SQLite repository for this employee.</p>
                        @else
                            <div class="space-y-4">
                                @foreach($requests as $req)
                                    @php
                                        $color = match($req->status) {
                                            'approved' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-450',
                                            'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-450 animate-pulse',
                                            'rejected' => 'bg-rose-50 text-rose-755 dark:bg-rose-500/10 dark:text-rose-455',
                                            default => 'bg-slate-50 text-slate-600'
                                        };
                                    @endphp
                                    <div class="flex items-center justify-between border border-slate-100 rounded-xl p-3 bg-slate-50/20 dark:border-slate-700 dark:bg-slate-900/10">
                                        <div class="space-y-0.5">
                                            <h4 class="text-xs font-bold text-slate-950 dark:text-white capitalize">{{ optional($req->policy)->name }}</h4>
                                            <p class="text-[10px] text-slate-400">{{ $req->start_date->format('M d, Y') }} - {{ $req->end_date->format('M d, Y') }} &bull; {{ $req->days_requested }} days</p>
                                        </div>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-bold uppercase {{ $color }}">
                                            {{ $req->status }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Request Time Off Shortcut -->
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Leave Service</h3>
                <p class="text-xs text-slate-450 leading-relaxed">Request new leave allowances, floating holidays or submit sickness absences for immediate logging.</p>
                <a href="{{ route('time-off.index') }}" class="w-full text-center inline-flex justify-center items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition">
                    <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                    <span>Open Leave System</span>
                </a>
            </div>
        </div>

        <!-- 4. PERFORMANCE TAB -->
        <div x-show="activeTab === 'performance'" x-cloak class="space-y-6">
            <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
                <div class="border-b border-slate-100 p-6 dark:border-slate-700">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Performance Appraisals</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Reviews securely gated by RBAC relations.</p>
                </div>
                
                <div class="p-6">
                    @if($reviews->isEmpty())
                        <div class="flex flex-col items-center justify-center py-6 text-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-750/50">
                                <i data-lucide="award" class="h-5 w-5"></i>
                            </div>
                            <h4 class="mt-3 text-xs font-bold text-slate-800 dark:text-slate-250">No review logs</h4>
                            <p class="text-[10px] text-slate-400 mt-0.5">This profile does not have any performance reviews recorded.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach($reviews as $rev)
                                @php
                                    $canView = $rev->canBeViewedBy($auth);
                                @endphp
                                
                                <div class="border border-slate-150 rounded-2xl p-5 bg-slate-50/20 dark:border-slate-750 dark:bg-slate-900/10 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-1">
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[9px] font-bold text-indigo-700 uppercase tracking-wide dark:bg-indigo-500/10 dark:text-indigo-400">
                                                {{ str_replace('_', ' ', $rev->type) }}
                                            </span>
                                            <span class="block text-[10px] text-slate-400 font-semibold mt-1">Reviewer: {{ $rev->reviewer->full_name ?? 'Manager' }}</span>
                                        </div>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[9px] font-bold uppercase text-slate-600 dark:bg-slate-750 dark:text-slate-400">
                                            {{ $rev->status }}
                                        </span>
                                    </div>
                                    
                                    @if($canView)
                                        <p class="text-xs text-slate-650 dark:text-slate-350 leading-relaxed font-semibold italic border-l-2 border-slate-200 pl-3 dark:border-slate-700">
                                            "{{ Str::limit($rev->content, 120, '...') }}"
                                        </p>
                                        <div class="flex justify-end">
                                            <a href="{{ route('performance.show', $rev->id) }}" class="inline-flex items-center gap-1 text-[10px] font-bold text-brand-600 hover:text-brand-700 transition dark:text-brand-400">
                                                <span>Read Full Appraisal</span>
                                                <i data-lucide="chevron-right" class="h-3 w-3"></i>
                                            </a>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2 p-3 bg-slate-100 rounded-xl dark:bg-slate-800 text-slate-450">
                                            <i data-lucide="lock" class="h-4 w-4 flex-shrink-0 text-slate-400"></i>
                                            <span class="text-[10px] font-medium leading-relaxed">RBAC Lock: This review is restricted and has not yet been shared with the employee.</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- 5. DOCUMENTS TAB -->
        <div x-show="activeTab === 'documents'" x-cloak class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <!-- Documents List -->
            <div class="md:col-span-2 rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
                <div class="border-b border-slate-100 p-6 dark:border-slate-700">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider"> paperworks & Signatures</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Manage credentials, onboarding paperwork, and e-signatures.</p>
                </div>
                
                <div class="p-6">
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 -my-4">
                        @foreach($documents as $doc)
                            @php
                                $statusClasses = match($doc['status']) {
                                    'Signed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                                    'Uploaded' => 'bg-indigo-50 text-indigo-755 dark:bg-indigo-500/10 dark:text-indigo-400',
                                    'Pending Signature' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-450 animate-pulse',
                                    default => 'bg-slate-50 text-slate-600'
                                };
                            @endphp
                            <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-750/50">
                                        <i data-lucide="file-text" class="h-5 w-5"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-950 dark:text-white">{{ $doc['name'] }}</h4>
                                        <p class="text-[10px] text-slate-400">{{ $doc['size'] }} &bull; Updated: {{ $doc['date'] }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3 pl-13 sm:pl-0">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-bold {{ $statusClasses }}">
                                        {{ $doc['status'] }}
                                    </span>
                                    
                                    @if($doc['status'] === 'Pending Signature' && $auth->id === $employee->id)
                                        <button type="button" class="rounded-xl bg-brand-600 px-3 py-1.5 text-[10px] font-bold text-slate-900 shadow hover:bg-brand-700 transition">
                                            Sign Document
                                        </button>
                                    @else
                                        <button type="button" class="rounded-xl border border-slate-200 bg-white p-1.5 text-slate-400 hover:text-slate-650 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-350 dark:hover:bg-slate-650" title="Download File">
                                            <i data-lucide="download" class="h-3.5 w-3.5"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Upload new document card -->
            <div class="rounded-2xl bg-white border border-slate-200/80 p-6 shadow-sm dark:bg-slate-800 dark:border-slate-800 space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">File Repository</h3>
                <p class="text-xs text-slate-450 leading-relaxed">Upload a new document (PDF, PNG, JPG under 10 MB) like ID papers or diplomas directly into your secured database.</p>
                <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-brand-500 transition cursor-pointer dark:border-slate-700">
                    <i data-lucide="upload-cloud" class="mx-auto h-8 w-8 text-slate-400"></i>
                    <span class="mt-2 block text-[10px] font-bold text-slate-500">Drag files here or browse</span>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
