@extends('layouts.hr-app')

@section('title', 'Time-Off Dashboard')
@section('breadcrumb', 'Time-Off')

@section('content')
@php $isTimeOffAdmin = auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'); @endphp
<div class="max-w-7xl mx-auto space-y-8" x-data="{ activeTab: '{{ $isTimeOffAdmin ? 'all_requests' : 'my_requests' }}' }">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Time-Off</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage your time off, view your balances, and approve team requests.
            </p>
        </div>
        @php
            $calcPolicies = $timeOffBalances->map(fn($b) => [
                'id' => $b->policy_id,
                'name' => optional($b->policy)->name,
                'remaining' => (float) max(0, ($b->opening_balance + $b->accrued + $b->carried_over + $b->adjusted) - $b->used - $b->pending),
            ])->values();
        @endphp
        <style>[x-cloak]{display:none!important}</style>
        <script>window.__calcPolicies = @json($calcPolicies);</script>

        <div class="mt-4 sm:mt-0 flex gap-3">
            <!-- Leave Balance Calculator -->
            <div x-data="{ open:false, policyId:'', start:'', end:'', policies: window.__calcPolicies || [],
                    workingDays(){ if(!this.start||!this.end) return 0; const s=new Date(this.start), e=new Date(this.end); if(e<s) return 0; let n=0; const d=new Date(s); while(d<=e){ const w=d.getDay(); if(w!==0&&w!==6) n++; d.setDate(d.getDate()+1);} return n; },
                    policy(){ return this.policies.find(p=>p.id==this.policyId) || null; },
                    remaining(){ const p=this.policy(); return p?p.remaining:0; },
                    after(){ const p=this.policy(); return p?(Math.round((p.remaining-this.workingDays())*100)/100):0; } }">
                <button type="button" @click="open=true" title="Leave balance calculator"
                        class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                    <i data-lucide="calculator" class="h-4 w-4 mr-2"></i> Calculator
                </button>

                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open=false"></div>
                    <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Leave Balance Calculator</h3>
                            <button type="button" @click="open=false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="h-5 w-5"></i></button>
                        </div>
                        <div class="space-y-4 text-left">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Leave type</label>
                                <select x-model="policyId" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    <option value="">Select…</option>
                                    <template x-for="p in policies" :key="p.id">
                                        <option :value="p.id" x-text="p.name + ' (' + p.remaining + ' left)'"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">From</label>
                                    <input type="date" x-model="start" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">To</label>
                                    <input type="date" x-model="end" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                </div>
                            </div>
                            <div class="rounded-xl bg-slate-50 p-4 space-y-2 dark:bg-slate-900/50">
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Working days requested</span><span class="font-bold text-slate-800 dark:text-white" x-text="workingDays()"></span></div>
                                <div class="flex justify-between text-sm"><span class="text-slate-500">Current balance</span><span class="font-bold text-slate-800 dark:text-white" x-text="remaining()"></span></div>
                                <div class="flex justify-between text-sm border-t border-slate-200 pt-2 dark:border-slate-700"><span class="text-slate-500">Balance after</span><span class="font-extrabold" :class="after() < 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="after()"></span></div>
                            </div>
                            <p x-show="after() < 0" class="text-xs font-semibold text-rose-600">This would exceed your available balance.</p>
                            <p class="text-[10px] text-slate-400">Counts Mon–Fri only (weekends excluded). Public holidays are not deducted here — this is an estimate.</p>
                        </div>
                    </div>
                </div>
            </div>

            <a href="{{ route('time-off.team-calendar') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                <i data-lucide="calendar" class="h-4 w-4 mr-2"></i> Team Calendar
            </a>
            <a href="{{ route('time-off.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Request Time Off
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3"><p class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('success') }}</p></div>
            </div>
        </div>
    @endif

    <!-- Balances Section (same source as the dashboard: the year's balance records) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($timeOffBalances as $balance)
            @php
                $total = $balance->opening_balance + $balance->accrued + $balance->carried_over + $balance->adjusted;
                $used = $balance->used;
                $pending = $balance->pending;
                $remaining = max(0, $total - $used - $pending);
                $percentUsed = $total > 0 ? min(100, ($used / $total) * 100) : 0;
                $percentPending = $total > 0 ? min(100 - $percentUsed, ($pending / $total) * 100) : 0;
                $unit = optional(auth()->user()->company)->leave_unit ?? 'days';
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ optional($balance->policy)->name ?? 'Leave' }}</h3>
                    <div class="text-2xl font-extrabold text-brand-600 dark:text-brand-400">{{ (float) $remaining }}</div>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 text-right mb-2">{{ $unit === 'hours' ? 'Hours' : 'Days' }} Remaining</div>

                <div class="w-full bg-slate-100 rounded-full h-2.5 mb-4 dark:bg-slate-700 flex overflow-hidden">
                    <div class="bg-brand-600 h-2.5 rounded-l-full" style="width: {{ $percentUsed }}%"></div>
                    <div class="bg-amber-400 h-2.5" style="width: {{ $percentPending }}%"></div>
                </div>

                <div class="flex justify-between text-xs text-slate-500 dark:text-slate-400">
                    <div>Used: <span class="font-bold text-slate-900 dark:text-white">{{ (float) $used }}</span></div>
                    <div>Pending: <span class="font-bold text-slate-900 dark:text-white">{{ (float) $pending }}</span></div>
                    <div>Allowance: <span class="font-bold text-slate-900 dark:text-white">{{ (float) $total }}</span></div>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 lg:col-span-3 bg-white rounded-2xl shadow-sm border border-slate-200/80 p-8 text-center text-sm text-slate-500 dark:bg-slate-800 dark:border-slate-700/80">
                No time-off balances found yet.
            </div>
        @endforelse
    </div>

    <!-- Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-8">
            @if($isTimeOffAdmin)
                <button @click="activeTab = 'all_requests'" :class="{'border-brand-500 text-brand-600 dark:text-brand-400': activeTab === 'all_requests', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': activeTab !== 'all_requests'}" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition">
                    All Requests (HR)
                </button>
            @endif
            @if($teamRequests->isNotEmpty() || $isTimeOffAdmin)
                <button @click="activeTab = 'team_requests'" :class="{'border-brand-500 text-brand-600 dark:text-brand-400': activeTab === 'team_requests', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': activeTab !== 'team_requests'}" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition flex items-center">
                    Approvals
                    @if($teamRequests->count() > 0)
                        <span class="ml-2 bg-brand-100 text-brand-600 py-0.5 px-2 rounded-full text-xs font-bold dark:bg-brand-900 dark:text-brand-300">{{ $teamRequests->count() }}</span>
                    @endif
                </button>
            @endif
            <button @click="activeTab = 'my_requests'" :class="{'border-brand-500 text-brand-600 dark:text-brand-400': activeTab === 'my_requests', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': activeTab !== 'my_requests'}" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition">
                My Requests
            </button>
        </nav>
    </div>

    <!-- My Requests Tab -->
    <div x-show="activeTab === 'my_requests'" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Policy</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Dates</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Days</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200 dark:bg-slate-800 dark:divide-slate-700">
                @forelse($myRequests as $request)
                    <tr>
                        <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white align-top">
                            <div>{{ $request->policy->name }}</div>
                            @if($request->reason)
                                <div class="text-xs font-normal text-slate-500 italic mt-1 max-w-[240px] line-clamp-2 dark:text-slate-400" title="{{ $request->reason }}">“{{ $request->reason }}”</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400 align-top">
                            {{ $request->start_date->format('M d, Y') }}@if($request->start_date != $request->end_date) - {{ $request->end_date->format('M d, Y') }}@endif
                            @if($request->duration_type === 'hourly')
                                <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-md ml-2 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $request->time_range }}</span>
                            @elseif($request->is_half_day)
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md ml-2 dark:bg-slate-700 dark:text-slate-300">Half Day ({{ ucfirst($request->half_day_period) }})</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $request->duration_label }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold {{ $request->status_color }}">
                                {{ ucfirst($request->status) }}
                            </span>
                            @if($request->status === 'approved' && $request->approver)
                                <div class="text-xs text-slate-400 mt-1">by {{ trim($request->approver->first_name.' '.$request->approver->last_name) }}@if($request->approved_at) · {{ $request->approved_at->format('M d') }}@endif</div>
                            @elseif($request->status === 'rejected' && $request->rejection_note)
                                <div class="text-xs text-rose-500 italic mt-1 max-w-xs truncate" title="{{ $request->rejection_note }}">"{{ $request->rejection_note }}"</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if($request->status === 'pending')
                                <form action="{{ route('time-off.destroy', $request) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">You have no time-off requests.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Approvals Tab -->
    @if($teamRequests->isNotEmpty() || auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
    <div x-show="activeTab === 'team_requests'" style="display: none;" class="space-y-4">
        @forelse($teamRequests as $request)
            @php
                $me = auth()->user();
                $pol = $request->policy;
                $stage = $request->approval_stage;
                $twoStage = $pol && in_array($pol->approval_type, ['both', 'manager_super']);
                $canDecide = $me->hasRole('super_admin')
                    || ($pol && $pol->approval_type === 'manager' && $me->managesUser($request->user_id))
                    || ($pol && $pol->approval_type === 'hr_admin' && $me->hasRole('hr_admin'))
                    || ($pol && in_array($pol->approval_type, ['both', 'manager_super']) && $stage === 'manager' && $me->managesUser($request->user_id))
                    || ($pol && $pol->approval_type === 'both' && $stage === 'hr_admin' && $me->hasRole('hr_admin'));
                $stageLabel = $twoStage
                    ? ($stage === 'manager' ? 'Awaiting Manager' : ($stage === 'hr_admin' ? 'Awaiting HR Admin' : 'Awaiting Super Admin'))
                    : null;
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col md:flex-row gap-6 dark:bg-slate-800 dark:border-slate-700/80" x-data="{ showReject: false }">
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold dark:bg-brand-900 dark:text-brand-300">
                                {{ substr($request->employee->first_name, 0, 1) }}{{ substr($request->employee->last_name, 0, 1) }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $request->employee->first_name }} {{ $request->employee->last_name }}</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $request->policy->name }}</p>
                                @if($stageLabel)
                                    <span class="inline-flex items-center gap-1 mt-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"><i data-lucide="git-merge" class="h-3 w-3"></i> {{ $stageLabel }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-extrabold text-slate-900 dark:text-white">{{ $request->duration_label }}</div>
                        </div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3 mb-3 border border-slate-100 dark:bg-slate-900 dark:border-slate-700/60">
                        <div class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            <i data-lucide="calendar" class="h-4 w-4 inline mr-1 text-slate-400"></i>
                            {{ $request->start_date->format('D, M d Y') }} 
                            @if($request->start_date != $request->end_date)
                                - {{ $request->end_date->format('D, M d Y') }}
                            @endif
                            @if($request->duration_type === 'hourly')
                                <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded ml-2 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $request->time_range }}</span>
                            @elseif($request->is_half_day)
                                <span class="text-xs bg-slate-200 text-slate-700 px-2 py-0.5 rounded ml-2 dark:bg-slate-700 dark:text-slate-300">Half Day ({{ ucfirst($request->half_day_period) }})</span>
                            @endif
                        </div>
                    </div>
                    @if($request->reason)
                        <p class="text-sm text-slate-600 dark:text-slate-400 italic">"{{ $request->reason }}"</p>
                    @endif
                </div>
                
                <div class="flex flex-col gap-2 md:w-48 justify-center border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6 dark:border-slate-700/60">
                    @if($canDecide)
                        <form action="{{ route('time-off.approve', $request) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full justify-center inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                                <i data-lucide="check" class="h-4 w-4 mr-1"></i> Approve
                            </button>
                        </form>
                        <button @click="showReject = !showReject" class="w-full justify-center inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                            <i data-lucide="x" class="h-4 w-4 mr-1"></i> Reject
                        </button>
                    @else
                        <div class="text-center text-xs text-slate-400 px-2">
                            <i data-lucide="clock" class="h-4 w-4 mx-auto mb-1"></i>
                            {{ $twoStage && $stage !== 'manager' ? 'Approved by manager — ' . strtolower($stageLabel) . '.' : 'Awaiting the assigned approver.' }}
                        </div>
                    @endif
                </div>
                
                <div x-show="showReject" style="display: none;" class="w-full mt-4 bg-rose-50 p-4 rounded-xl border border-rose-100 dark:bg-rose-500/10 dark:border-rose-500/20">
                    <form action="{{ route('time-off.reject', $request) }}" method="POST">
                        @csrf
                        <label class="block text-xs font-bold text-rose-800 uppercase mb-2 dark:text-rose-400">Rejection Note (Required)</label>
                        <textarea name="rejection_note" required rows="2" class="w-full rounded-xl border-rose-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm dark:bg-slate-900 dark:border-rose-500/30 dark:text-white mb-3" placeholder="Please provide a reason for rejecting this request..."></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showReject = false" class="px-3 py-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400">Cancel</button>
                            <button type="submit" class="px-3 py-1.5 bg-rose-600 text-white text-sm font-bold rounded-lg hover:bg-rose-700 shadow-sm">Confirm Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="check-circle" class="h-6 w-6 text-emerald-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">All caught up!</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">No pending time-off requests require your approval.</p>
            </div>
        @endforelse
    </div>
    @endif

    <!-- All Requests (HR) Tab -->
    @if(auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
    <div x-show="activeTab === 'all_requests'" style="display: none;" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <div class="p-4 border-b border-slate-200 bg-slate-50 dark:bg-slate-900/50 dark:border-slate-700 flex gap-4 items-center">
            <form action="{{ route('time-off.index') }}" method="GET" class="flex gap-4 items-center">
                <select name="status" onchange="this.form.submit()" class="rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm py-1.5 dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
                <select name="policy_id" onchange="this.form.submit()" class="rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm py-1.5 dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    <option value="all">All Policies</option>
                    @foreach($allPolicies as $policy)
                        <option value="{{ $policy->id }}" {{ request('policy_id') == $policy->id ? 'selected' : '' }}>{{ $policy->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Policy</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Dates</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Days</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Decided By</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200 dark:bg-slate-800 dark:divide-slate-700">
                @forelse($allRequests as $request)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white align-top">
                            {{ $request->employee->first_name }} {{ $request->employee->last_name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 align-top">
                            <div class="font-medium text-slate-700 dark:text-slate-300">{{ $request->policy->name }}</div>
                            @if(in_array($request->status, ['pending', 'approved']))
                                <form action="{{ route('time-off.change-policy', $request) }}" method="POST" class="mt-1.5 inline-block">
                                    @csrf
                                    <select name="policy_id" title="Move this leave to another category" data-current="{{ $request->policy_id }}"
                                            onchange="if (this.value !== this.dataset.current && confirm('Move this leave to “' + this.options[this.selectedIndex].text + '”? Balances will be adjusted.')) { this.form.submit(); } else { this.value = this.dataset.current; }"
                                            class="text-xs rounded-lg border border-slate-200 py-1 pl-2 pr-6 font-semibold text-slate-600 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300">
                                        @foreach($allPolicies as $p)
                                            <option value="{{ $p->id }}" {{ $p->id == $request->policy_id ? 'selected' : '' }}>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                            @if($request->reason)
                                <div class="text-xs text-slate-500 italic mt-1 max-w-[240px] line-clamp-2 dark:text-slate-400" title="{{ $request->reason }}">“{{ $request->reason }}”</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ (float) $request->days_requested }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold {{ $request->status_color }}">
                                {{ ucfirst($request->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($request->status === 'approved' && $request->approver)
                                <div class="text-slate-800 font-semibold dark:text-slate-200">{{ trim($request->approver->first_name.' '.$request->approver->last_name) }}</div>
                                <div class="text-xs text-emerald-600 dark:text-emerald-400">Approved{{ $request->approved_at ? ' · '.$request->approved_at->format('M d, Y g:i A') : '' }}</div>
                            @elseif($request->status === 'rejected' && $request->rejecter)
                                <div class="text-slate-800 font-semibold dark:text-slate-200">{{ trim($request->rejecter->first_name.' '.$request->rejecter->last_name) }}</div>
                                <div class="text-xs text-rose-600 dark:text-rose-400">Rejected{{ $request->rejected_at ? ' · '.$request->rejected_at->format('M d, Y g:i A') : '' }}</div>
                                @if($request->rejection_note)
                                    <div class="text-xs text-slate-400 italic mt-0.5 max-w-xs truncate" title="{{ $request->rejection_note }}">"{{ $request->rejection_note }}"</div>
                                @endif
                            @else
                                <span class="text-xs text-slate-400">— Awaiting decision</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            @if(in_array($request->status, ['pending', 'approved']))
                                <form action="{{ route('time-off.destroy', $request) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($allRequests->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $allRequests->links() }}
            </div>
        @endif
    </div>
    @endif
</div>
@endsection
