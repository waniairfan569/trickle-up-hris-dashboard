@extends('layouts.hr-app')

@section('title', 'Time-Off Dashboard')
@section('breadcrumb', 'Time-Off')

@section('content')
@php $isTimeOffAdmin = auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'); @endphp
<div class="space-y-8" x-data="{ activeTab: '{{ ($isTimeOffAdmin || $teamRequests->isNotEmpty()) ? 'team_requests' : 'my_requests' }}', ret: { open: false, action: '', min: '', max: '', label: '' } }">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Time-Off</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage your time off, view your balances, and approve team requests.
            </p>
        </div>
        @php
            // Leave types for the calculator. Prefer the viewer's own balances
            // (real "remaining" figures); if they hold none (e.g. an admin who
            // isn't enrolled in any policy) fall back to the active policies so
            // the dropdown is never empty and the tool still works.
            $calcPolicies = $timeOffBalances->isNotEmpty()
                ? $timeOffBalances->map(fn($b) => [
                    'id' => $b->policy_id,
                    'name' => optional($b->policy)->name,
                    'remaining' => (float) max(0, ($b->opening_balance + $b->accrued + $b->carried_over + $b->adjusted) - $b->used - $b->pending),
                ])->values()
                : $allPolicies->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'remaining' => 0.0,
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
                        class="btn-outline">
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

            <a href="{{ route('time-off.team-calendar') }}" class="btn-outline">
                <i data-lucide="calendar" class="h-4 w-4 mr-2"></i> Team Calendar
            </a>
            <a href="{{ route('time-off.create') }}" class="btn-brand">
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

    <!-- Tabs (Approvals first & default, then All Requests, then My Requests) -->
    <div class="inline-flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1 dark:bg-slate-800/80">
        @php $tabBase = 'inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-semibold transition'; @endphp
        @if($teamRequests->isNotEmpty() || $isTimeOffAdmin)
            <button @click="activeTab = 'team_requests'" :class="activeTab === 'team_requests' ? 'bg-white text-brand-600 shadow-sm dark:bg-slate-700 dark:text-brand-400' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'" class="{{ $tabBase }}">
                <i data-lucide="inbox" class="h-4 w-4"></i> Approvals
                @if($teamRequests->count() > 0)
                    <span class="rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">{{ $teamRequests->count() }}</span>
                @endif
            </button>
        @endif
        @if($isTimeOffAdmin)
            <button @click="activeTab = 'all_requests'" :class="activeTab === 'all_requests' ? 'bg-white text-brand-600 shadow-sm dark:bg-slate-700 dark:text-brand-400' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'" class="{{ $tabBase }}">
                <i data-lucide="list" class="h-4 w-4"></i> All Requests <span class="text-[11px] font-normal opacity-70">HR</span>
            </button>
        @endif
        <button @click="activeTab = 'my_requests'" :class="activeTab === 'my_requests' ? 'bg-white text-brand-600 shadow-sm dark:bg-slate-700 dark:text-brand-400' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'" class="{{ $tabBase }}">
            <i data-lucide="user" class="h-4 w-4"></i> My Requests
        </button>
    </div>

    <!-- My Requests Tab -->
    @php
        $nowRef      = now();
        $weekStartR  = $nowRef->copy()->startOfWeek();  $weekEndR  = $nowRef->copy()->endOfWeek();
        $monthStartR = $nowRef->copy()->startOfMonth(); $monthEndR = $nowRef->copy()->endOfMonth();
        $yearStartR  = $nowRef->copy()->startOfYear();  $yearEndR  = $nowRef->copy()->endOfYear();
        $overlaps = fn ($r, $s, $e) => $r->start_date->lte($e) && $r->end_date->gte($s);
        // Each request as plain data for the client-side filter: start/end + which
        // preset periods it overlaps. Order matches the @forelse loop below.
        $rowsForJs = $myRequests->map(fn ($r) => [
            's' => $r->start_date->toDateString(),
            'e' => $r->end_date->toDateString(),
            'w' => $overlaps($r, $weekStartR, $weekEndR),
            'm' => $overlaps($r, $monthStartR, $monthEndR),
            'y' => $overlaps($r, $yearStartR, $yearEndR),
        ])->values();
    @endphp
    <script>
        function myRequestsFilter() {
            return {
                reqFilter: 'all',
                pickMonth: '',
                pickEnd: '',
                rows: @json($rowsForJs),
                get pickLabel() {
                    if (!this.pickMonth) return 'Pick month';
                    const [y, m] = this.pickMonth.split('-').map(Number);
                    return new Date(y, m - 1, 1).toLocaleString('en-US', { month: 'short' }) + ' ' + y;
                },
                setMonth(v) {
                    this.pickMonth = v || '';
                    if (this.pickMonth) {
                        const [y, m] = this.pickMonth.split('-').map(Number);
                        const last = new Date(y, m, 0).getDate();
                        this.pickEnd = this.pickMonth + '-' + String(last).padStart(2, '0');
                        this.reqFilter = 'pick';
                    } else {
                        this.pickEnd = '';
                        this.reqFilter = 'all';
                    }
                },
                preset(key) { this.reqFilter = key; this.pickMonth = ''; this.pickEnd = ''; },
                matches(r) {
                    if (this.reqFilter === 'pick')  return this.pickMonth !== '' && r.s <= this.pickEnd && r.e >= this.pickMonth + '-01';
                    if (this.reqFilter === 'week')  return r.w;
                    if (this.reqFilter === 'month') return r.m;
                    if (this.reqFilter === 'year')  return r.y;
                    return true; // 'all'
                },
                get anyVisible() { return this.rows.some(r => this.matches(r)); },
            };
        }
    </script>
    <div x-show="activeTab === 'my_requests'" x-data="myRequestsFilter()" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-6 py-4 border-b border-slate-200/80 dark:border-slate-700/60">
            <h3 class="text-sm font-bold text-slate-800 dark:text-white">My Requests</h3>
            <div class="flex flex-wrap items-center gap-2 self-start">
                <div class="inline-flex rounded-xl border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-slate-900/40">
                    @foreach(['all' => 'All', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year'] as $key => $label)
                        <button type="button" @click="preset('{{ $key }}')"
                                :class="reqFilter === '{{ $key }}' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition whitespace-nowrap">{{ $label }}</button>
                    @endforeach
                </div>
                {{-- Pick any specific month --}}
                <label class="relative inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold cursor-pointer transition whitespace-nowrap"
                       :class="reqFilter === 'pick' ? 'border-brand-300 bg-brand-50 text-brand-700 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-400' : 'border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-700 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/40'">
                    <i data-lucide="calendar-days" class="h-3.5 w-3.5"></i>
                    <span x-text="pickLabel"></span>
                    <input type="month" class="absolute inset-0 h-full w-full opacity-0 cursor-pointer"
                           @click="if ($event.target.showPicker) { try { $event.target.showPicker(); } catch (e) {} }"
                           @change="setMonth($event.target.value)" :value="pickMonth" title="Pick a month">
                </label>
            </div>
        </div>
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
                    <tr x-show="matches(rows[{{ $loop->index }}])">
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
                            @php
                                $ret = ($myReturns ?? collect())[$request->id] ?? null;
                                $eligibleReturn = $request->status === 'approved'
                                    && $request->duration_type !== 'hourly' && ! $request->is_half_day
                                    && $request->start_date && $request->end_date
                                    && $request->end_date->gt($request->start_date)
                                    && $request->end_date->gte(today());
                                $retMin = $request->start_date ? $request->start_date->toDateString() : today()->toDateString();
                            @endphp
                            @if($request->status === 'pending')
                                <form action="{{ route('time-off.destroy', $request) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Cancel this time-off request?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 transition dark:border-rose-500/30 dark:text-rose-400 dark:hover:bg-rose-500/10"><i data-lucide="x" class="h-3.5 w-3.5"></i> Cancel</button>
                                </form>
                            @elseif($ret && $ret->status === 'pending')
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400"><i data-lucide="clock" class="h-3.5 w-3.5"></i> Return requested</span>
                            @elseif($ret && $ret->status === 'approved')
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400"><i data-lucide="calendar-check" class="h-3.5 w-3.5"></i> Returned early</span>
                            @elseif($eligibleReturn)
                                <button type="button"
                                        @click="ret.action='{{ url('time-off/'.$request->id.'/return') }}'; ret.min='{{ $retMin }}'; ret.max='{{ $request->end_date->toDateString() }}'; ret.label='{{ $request->policy->name }} · {{ $request->start_date->format('M d') }} – {{ $request->end_date->format('M d, Y') }}'; ret.open=true"
                                        class="text-brand-600 hover:text-brand-700 dark:text-brand-400">Return early</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">You have no time-off requests.</td>
                    </tr>
                @endforelse
                @if($myRequests->count() > 0)
                    <tr x-show="!anyVisible" x-cloak>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No time-off requests match this filter.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Approvals Tab -->
    @if($teamRequests->isNotEmpty() || auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
    <div x-show="activeTab === 'team_requests'" style="display: none;" class="space-y-4">

        {{-- Early-return (curtailment) requests awaiting a decision --}}
        @if(($pendingReturns ?? collect())->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="calendar-check" class="h-4 w-4 text-brand-500"></i> Early-return requests
                        <span class="rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-[10px] font-bold dark:bg-amber-500/20 dark:text-amber-300">{{ $pendingReturns->count() }}</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Approving credits the unused days back and shortens the leave.</p>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @foreach($pendingReturns as $lr)
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" x-data="{ showReject: false }">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-white">{{ optional($lr->employee)->full_name ?? 'Employee' }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ optional($lr->request->policy)->name ?? 'Leave' }} ·
                                    back {{ $lr->return_date->format('D, d M Y') }} ·
                                    <span class="font-semibold text-brand-600 dark:text-brand-400">{{ $lr->days_returned }} day(s) to credit</span>
                                </p>
                                @if($lr->request)
                                    <p class="text-[11px] text-slate-400 mt-0.5">Original leave: {{ $lr->request->start_date->format('d M') }} – {{ $lr->request->end_date->format('d M Y') }}</p>
                                @endif
                                @if($lr->reason)
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 italic">“{{ $lr->reason }}”</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <form action="{{ route('time-off.return.approve', $lr) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="rounded-xl bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400">Approve</button>
                                </form>
                                <button type="button" @click="showReject = !showReject" class="rounded-xl bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400">Decline</button>
                            </div>
                            <form x-show="showReject" x-cloak action="{{ route('time-off.return.reject', $lr) }}" method="POST" class="w-full sm:mt-2 flex items-center gap-2">
                                @csrf
                                <input type="text" name="review_note" maxlength="500" placeholder="Reason (optional)" class="flex-1 rounded-xl border border-slate-300 px-3 py-1.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-rose-700">Confirm decline</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80" x-data="{ showReject: false }">
                <div class="flex flex-col md:flex-row gap-6">
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
                            <button type="submit" class="btn-success btn-block">
                                <i data-lucide="check" class="h-4 w-4 mr-1"></i> Approve
                            </button>
                        </form>
                        <button type="button" @click="showReject = !showReject" class="btn-outline btn-block">
                            <i data-lucide="x" class="h-4 w-4 mr-1"></i> Reject
                        </button>
                    @else
                        <div class="text-center text-xs text-slate-400 px-2">
                            <i data-lucide="clock" class="h-4 w-4 mx-auto mb-1"></i>
                            {{ $twoStage && $stage !== 'manager' ? 'Approved by manager — ' . strtolower($stageLabel) . '.' : 'Awaiting the assigned approver.' }}
                        </div>
                    @endif
                </div>
                </div>

                {{-- Rejection note — full-width panel below the row --}}
                <div x-show="showReject" x-cloak style="display:none;" class="mt-5 rounded-xl border border-rose-200 bg-rose-50/70 p-4 dark:bg-rose-500/10 dark:border-rose-500/20">
                    <form action="{{ route('time-off.reject', $request) }}" method="POST">
                        @csrf
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="message-square-x" class="h-4 w-4 text-rose-500"></i>
                            <label class="text-xs font-bold uppercase tracking-wide text-rose-700 dark:text-rose-400">Rejection note <span class="font-normal normal-case text-rose-400">· required</span></label>
                        </div>
                        <textarea name="rejection_note" required rows="2" class="w-full rounded-xl border border-rose-300 bg-white px-3.5 py-2.5 text-sm shadow-sm placeholder:text-rose-300 focus:border-rose-500 focus:ring-2 focus:ring-rose-200 focus:outline-none dark:bg-slate-900 dark:border-rose-500/30 dark:text-white dark:placeholder:text-rose-500/40" placeholder="Let {{ $request->employee->first_name }} know why this request can’t be approved…"></textarea>
                        <div class="mt-3 flex items-center justify-end gap-2">
                            <button type="button" @click="showReject = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-500 hover:text-slate-800 hover:bg-white transition dark:text-slate-400 dark:hover:bg-slate-700">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rose-700 transition"><i data-lucide="x" class="h-4 w-4"></i> Confirm Reject</button>
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
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur border-b border-slate-200 dark:bg-slate-900/85 dark:border-slate-700">
                <tr>
                    @foreach(['Employee','Policy','Dates','Days','Status','Decided By'] as $h)
                        <th class="px-6 py-3 text-left text-[11px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">{{ $h }}</th>
                    @endforeach
                    <th class="px-6 py-3 text-right text-[11px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($allRequests as $request)
                    <tr class="hover:bg-slate-50/70 even:bg-slate-50/30 transition dark:hover:bg-slate-700/30 dark:even:bg-slate-900/20">
                        <td class="px-6 py-4 whitespace-nowrap align-top">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-400 to-indigo-400 text-[11px] font-bold text-white">{{ strtoupper(substr($request->employee->first_name,0,1).substr($request->employee->last_name,0,1)) }}</span>
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $request->employee->first_name }} {{ $request->employee->last_name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 align-top">
                            <div class="font-medium text-slate-700 dark:text-slate-300">{{ $request->policy->name }}</div>
                            @if($request->status === 'pending')
                                {{-- Open request: quick reclassify while triaging. --}}
                                <form action="{{ route('time-off.change-policy', $request) }}" method="POST" class="mt-1.5 inline-block">
                                    @csrf
                                    <select name="policy_id" title="Move this leave to another category" data-current="{{ $request->policy_id }}"
                                            onchange="if (this.value !== this.dataset.current && confirm('Move this leave to “' + this.options[this.selectedIndex].text + '”? Balances will be adjusted.')) { this.form.submit(); } else { this.value = this.dataset.current; }"
                                            class="text-xs rounded-lg border border-slate-200 py-1 pl-2 pr-6 font-semibold text-slate-600 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300">
                                        @foreach($movePolicies as $p)
                                            <option value="{{ $p->id }}" {{ $p->id == $request->policy_id ? 'selected' : '' }}>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @elseif($request->status === 'approved')
                                {{-- Decided record: reclassifying is deliberate — hidden behind a toggle
                                     so a stray click can't reassign a historical leave. --}}
                                <div x-data="{ editType: false }" class="mt-1">
                                    <button type="button" x-show="!editType" @click="editType = true"
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-slate-400 hover:text-brand-600 dark:hover:text-brand-400">
                                        <i data-lucide="pencil" class="h-3 w-3"></i> Change type
                                    </button>
                                    <form x-show="editType" x-cloak action="{{ route('time-off.change-policy', $request) }}" method="POST" class="inline-flex items-center gap-1">
                                        @csrf
                                        <select name="policy_id" title="Reclassify this approved leave" data-current="{{ $request->policy_id }}"
                                                onchange="if (this.value !== this.dataset.current && confirm('Reclassify this APPROVED leave to “' + this.options[this.selectedIndex].text + '”? Balances will be adjusted.')) { this.form.submit(); } else { this.value = this.dataset.current; }"
                                                class="text-xs rounded-lg border border-slate-200 py-1 pl-2 pr-6 font-semibold text-slate-600 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300">
                                            @foreach($movePolicies as $p)
                                                <option value="{{ $p->id }}" {{ $p->id == $request->policy_id ? 'selected' : '' }}>{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" @click="editType = false" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">Cancel</button>
                                    </form>
                                </div>
                            @endif
                            @if($request->reason)
                                <div class="text-xs text-slate-500 italic mt-1 max-w-[240px] line-clamp-2 dark:text-slate-400" title="{{ $request->reason }}">“{{ $request->reason }}”</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $request->start_date->format('M d, Y') }}@if($request->start_date != $request->end_date) - {{ $request->end_date->format('M d, Y') }}@endif
                            @if($request->duration_type === 'hourly')
                                <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-md ml-2 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $request->time_range }}</span>
                            @elseif($request->is_half_day)
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md ml-2 dark:bg-slate-700 dark:text-slate-300">Half Day ({{ ucfirst($request->half_day_period) }})</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-700 tabular-nums dark:text-slate-300">
                            {{ $request->duration_label }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php $sc = ['approved'=>'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400','pending'=>'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400','rejected'=>'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400','cancelled'=>'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'][$request->status] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'; @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold {{ $sc }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ ucfirst($request->status) }}
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
                                <form action="{{ route('time-off.destroy', $request) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Cancel this {{ $request->status }} request?@if($request->status === 'approved') The reserved balance is refunded and any on-leave days in this period revert to their prior status — including days already in the past.@endif')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50 transition dark:border-rose-500/30 dark:text-rose-400 dark:hover:bg-rose-500/10"><i data-lucide="x" class="h-3.5 w-3.5"></i> Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-300 dark:bg-slate-900"><i data-lucide="calendar-off" class="h-6 w-6"></i></div>
                            <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">No requests found</p>
                            <p class="text-xs text-slate-400">Try changing the status or policy filter.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($allRequests->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $allRequests->links() }}
            </div>
        @endif
    </div>
    @endif

    {{-- Return-early (curtailment) request modal --}}
    <template x-teleport="body">
        <div x-show="ret.open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="ret.open = false"></div>
            <form :action="ret.action" method="POST"
                  class="relative w-full max-w-md bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/70 dark:border-slate-700 overflow-hidden"
                  @keydown.escape.window="ret.open = false">
                @csrf
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="calendar-check" class="h-4 w-4 text-brand-500"></i> Return early
                    </h3>
                    <button type="button" @click="ret.open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400" x-text="ret.label"></p>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">First day back at work</label>
                        <input type="date" name="return_date" :min="ret.min" :max="ret.max" required
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <p class="text-[11px] text-slate-400 mt-1">Pick the first day you're back at work — any day of your leave. Every leave day from then to the end is credited back once HR approves.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Reason <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea name="reason" rows="2" maxlength="500" placeholder="Why are you coming back early?"
                                  class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                    <button type="button" @click="ret.open = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="btn-brand"><i data-lucide="send" class="h-4 w-4"></i> Send to HR</button>
                </div>
            </form>
        </div>
    </template>
</div>
@endsection
