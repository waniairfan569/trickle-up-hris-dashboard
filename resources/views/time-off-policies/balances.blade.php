@extends('layouts.hr-app')

@section('title', 'Manage Balances')
@section('breadcrumb', 'Manage Balances')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{ adjustModalOpen: false, currentUserId: null, currentUserName: '' }">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('time-off-policies.index') }}" class="text-brand-600 hover:underline">Policies</a> / 
                {{ $timeOffPolicy->name }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage employee assignments and balances for the {{ $year }} year.
            </p>
        </div>
        
        <div class="mt-4 sm:mt-0">
            <form method="GET" action="{{ route('time-off-policies.balances', $timeOffPolicy) }}" class="flex items-center gap-2">
                <select name="year" onchange="this.form.submit()" class="rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    @for($y = date('Y') - 1; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
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
    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        
        <div class="p-6 bg-slate-50 border-b border-slate-100 dark:bg-slate-900/50 dark:border-slate-700/60">
            <form action="{{ route('time-off-policies.assign', $timeOffPolicy) }}" method="POST">
                @csrf
                <div class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1" x-data="{
                            search: '',
                            selected: 0,
                            allSelected: false,
                            boxes() { return Array.from(this.$refs.list.querySelectorAll('input.emp-cb')); },
                            visible() { return this.boxes().filter(b => b.closest('label').style.display !== 'none'); },
                            toggleAll(checked) { this.visible().forEach(b => b.checked = checked); this.refresh(); },
                            refresh() { this.selected = this.boxes().filter(b => b.checked).length; const v = this.visible(); this.allSelected = v.length > 0 && v.every(b => b.checked); }
                        }" x-init="$nextTick(() => refresh())">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2 dark:text-slate-300">Assign Employees to Policy</label>
                        <div class="rounded-xl border border-slate-300 bg-white overflow-hidden dark:bg-slate-800 dark:border-slate-600">
                            <!-- Search + Select all -->
                            <div class="flex items-center gap-2 px-3 py-2 border-b border-slate-200 bg-slate-50 dark:bg-slate-900/40 dark:border-slate-700">
                                <div class="relative flex-1">
                                    <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400"></i>
                                    <input type="text" x-model="search" @input="$nextTick(() => refresh())" placeholder="Search employees..."
                                           class="w-full rounded-lg border border-slate-200 pl-8 pr-3 py-1.5 text-xs focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white">
                                </div>
                                <label class="flex items-center gap-1.5 text-xs font-bold text-slate-600 cursor-pointer whitespace-nowrap dark:text-slate-300">
                                    <input type="checkbox" @change="toggleAll($event.target.checked)" :checked="allSelected"
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-4 w-4">
                                    Select all
                                </label>
                            </div>
                            <!-- Employee list (names only) -->
                            <div x-ref="list" class="max-h-48 overflow-y-auto px-2 py-1.5 space-y-0.5">
                                @forelse($availableUsers as $user)
                                    @php $nm = trim($user->first_name . ' ' . $user->last_name) ?: 'Unnamed'; @endphp
                                    <label data-name="{{ mb_strtolower($nm) }}"
                                           x-show="search === '' || $el.dataset.name.includes(search.toLowerCase())"
                                           class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50 cursor-pointer dark:hover:bg-slate-700/40">
                                        <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" @change="refresh()"
                                               class="emp-cb rounded border-slate-300 text-brand-600 focus:ring-brand-500 h-4 w-4">
                                        <span class="text-sm text-slate-700 dark:text-slate-200">{{ $nm }}</span>
                                    </label>
                                @empty
                                    <p class="text-xs text-slate-400 px-2 py-3 text-center">All employees are already assigned to this policy.</p>
                                @endforelse
                            </div>
                            <!-- Footer count -->
                            <div class="px-3 py-1.5 border-t border-slate-200 bg-slate-50 text-[11px] font-semibold text-slate-500 dark:bg-slate-900/40 dark:border-slate-700">
                                <span x-text="selected"></span> selected
                            </div>
                        </div>
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2 dark:text-slate-300">Custom Days (Optional)</label>
                        <input type="number" step="0.5" name="custom_days_per_year" placeholder="Default: {{ $timeOffPolicy->days_per_year }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <button type="submit" class="h-10 rounded-xl bg-brand-600 px-4 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 mb-1">Assign & Create Balances</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 bg-white dark:bg-slate-800 dark:border-slate-700">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Employee</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right" title="Granted on Jan 1st">Opening</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right" title="Accrued so far">Accrued</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right" title="Carried from last year">Carried</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right" title="Manual Admin Adjustments">Adjusted</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right" title="Used days">Used</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right" title="Pending requests">Pending</th>
                        <th class="px-4 py-4 text-xs font-bold text-slate-900 uppercase tracking-wider text-right dark:text-white bg-slate-50 dark:bg-slate-900">Remaining</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($assignedUsers as $user)
                        @php
                            $balance = $balances->get($user->id);
                        @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $user->first_name }} {{ $user->last_name }}</div>
                                @if($user->pivot->custom_days_per_year)
                                    <div class="text-[10px] text-brand-600 font-bold dark:text-brand-400">Custom rule: {{ (float) $user->pivot->custom_days_per_year }} / yr</div>
                                @endif
                            </td>
                            
                            @if($balance)
                                <td class="px-4 py-4 text-sm text-slate-600 text-right dark:text-slate-400">{{ (float) $balance->opening_balance }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600 text-right dark:text-slate-400">{{ (float) $balance->accrued }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600 text-right dark:text-slate-400">{{ (float) $balance->carried_over }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600 text-right dark:text-slate-400">
                                    <span class="{{ $balance->adjusted != 0 ? 'font-bold text-brand-600' : '' }}">
                                        {{ $balance->adjusted > 0 ? '+' : '' }}{{ (float) $balance->adjusted }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm font-bold text-rose-600 text-right dark:text-rose-400">{{ (float) $balance->used }}</td>
                                <td class="px-4 py-4 text-sm font-bold text-amber-600 text-right dark:text-amber-400">{{ (float) $balance->pending }}</td>
                                <td class="px-4 py-4 text-sm font-extrabold text-emerald-600 text-right bg-slate-50 dark:bg-slate-900 dark:text-emerald-400 text-lg">
                                    {{ (float) $balance->remaining }}
                                </td>
                            @else
                                <td colspan="7" class="px-4 py-4 text-sm text-slate-400 text-center italic">No balance record for {{ $year }}</td>
                            @endif

                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @if($balance)
                                        <button @click="adjustModalOpen = true; currentUserId = {{ $user->id }}; currentUserName = '{{ addslashes($user->first_name . ' ' . $user->last_name) }}'" class="text-brand-600 hover:text-brand-800 text-sm font-bold transition">Adjust</button>
                                    @endif
                                    <form action="{{ route('time-off-policies.unassign', $timeOffPolicy) }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $user->id }}">
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-bold transition">Unassign</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No employees assigned to this policy.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Adjust Balance Modal -->
    <div x-show="adjustModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-slate-900/75 backdrop-blur-sm" @click="adjustModalOpen = false"></div>

            <div class="relative inline-block w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white rounded-2xl shadow-xl dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Adjust Balance</h3>
                    <button @click="adjustModalOpen = false" class="text-slate-400 hover:text-slate-500 focus:outline-none">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
                
                <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">You are adjusting the balance for <strong x-text="currentUserName" class="text-slate-900 dark:text-white"></strong>.</p>
                
                <form action="{{ route('time-off-policies.adjust-balance', $timeOffPolicy) }}" method="POST">
                    @csrf
                    <input type="hidden" name="user_id" x-model="currentUserId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Adjustment Amount (Days) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.5" name="amount" required placeholder="e.g. 1.5 or -2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <p class="mt-1 text-[10px] text-slate-500">Use negative numbers to reduce balance.</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Reason / Note</label>
                            <input type="text" name="note" placeholder="e.g. Corrected carried over calculation" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="adjustModalOpen = false" class="rounded-xl px-4 py-2 bg-white border border-slate-300 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300">Cancel</button>
                        <button type="submit" class="rounded-xl px-4 py-2 bg-brand-600 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700">Apply Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
