@extends('layouts.hr-app')

@section('title', 'Leave Encashments')
@section('breadcrumb', 'Leave Encashments')

@section('content')
<style>[x-cloak]{display:none!important}</style>
@php $fmtD = fn ($d) => rtrim(rtrim(number_format((float) $d, 1), '0'), '.'); @endphp
<div class="max-w-6xl mx-auto space-y-6" x-data="{ selected: [], showPay: false }">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="banknote" class="h-6 w-6 text-brand-500"></i> Leave Encashments
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Year-end encashment records — approve, reject, or mark paid.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    {{-- Year tabs + filters --}}
    <div class="flex flex-wrap items-center gap-2">
        @foreach($years as $y)
            <a href="{{ route('leave-encashments.index', ['year' => $y]) }}" class="rounded-xl px-4 py-2 text-sm font-bold border {{ $y == $year ? 'bg-brand-600 text-slate-900 border-brand-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600' }}">{{ $y }}–{{ substr($y + 1, 2) }}</a>
        @endforeach
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="year" value="{{ $year }}">
            <select name="status" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                @foreach(['all' => 'All statuses', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('status', 'all') === $v)>{{ $l }}</option>
                @endforeach
            </select>
            <select name="policy_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                <option value="all">All policies</option>
                @foreach($policies as $p)<option value="{{ $p->id }}" @selected(request('policy_id') == $p->id)>{{ $p->name }}</option>@endforeach
            </select>
            <select name="period" onchange="this.form.submit()" title="Filter by when records were processed" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                <option value="all" @selected(($period ?? 'all') === 'all')>Full year</option>
                <option value="h1" @selected(($period ?? '') === 'h1')>First half (Jan–Jun)</option>
                <option value="h2" @selected(($period ?? '') === 'h2')>Second half (Jul–Dec)</option>
                @foreach(range(1,12) as $m)
                    @php $mm = str_pad($m, 2, '0', STR_PAD_LEFT); @endphp
                    <option value="{{ $mm }}" @selected(($period ?? '') === $mm)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                @endforeach
            </select>
            @php $exportParams = array_filter(['year' => $year, 'status' => request('status'), 'policy_id' => request('policy_id'), 'period' => ($period ?? null) !== 'all' ? ($period ?? null) : null]); @endphp
            <div class="inline-flex rounded-xl border border-slate-300 overflow-hidden dark:border-slate-600" x-data>
                <a href="{{ route('leave-encashments.export', $exportParams) }}" class="inline-flex items-center gap-1.5 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 border-r border-slate-200 dark:border-slate-600"><i data-lucide="sheet" class="h-4 w-4"></i> Excel</a>
                <a href="{{ route('leave-encashments.export-pdf', $exportParams) }}" class="inline-flex items-center gap-1.5 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 border-r border-slate-200 dark:border-slate-600"><i data-lucide="file-down" class="h-4 w-4"></i> PDF</a>
                <a href="{{ route('leave-encashments.export-pdf', $exportParams + ['preview' => 1]) }}" target="_blank" class="inline-flex items-center gap-1.5 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-4 w-4"></i> Preview</a>
            </div>
        </form>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Pending ({{ $summary['pending_count'] }})</p>
            <p class="text-xl font-extrabold text-slate-800 dark:text-white mt-1">PKR {{ number_format($summary['pending_amount'], 0) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-600">Approved</p>
            <p class="text-xl font-extrabold text-slate-800 dark:text-white mt-1">PKR {{ number_format($summary['approved_amount'], 0) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Paid</p>
            <p class="text-xl font-extrabold text-slate-800 dark:text-white mt-1">PKR {{ number_format($summary['paid_amount'], 0) }}</p>
        </div>
    </div>

    {{-- Bulk mark paid --}}
    <div x-show="selected.length" x-cloak class="rounded-xl bg-slate-800 text-white px-4 py-3 flex flex-wrap items-center gap-3">
        <span class="text-sm font-bold"><span x-text="selected.length"></span> selected</span>
        <button type="button" @click="showPay = true" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-bold hover:bg-emerald-700">Mark paid…</button>
    </div>
    <form method="POST" action="{{ route('leave-encashments.mark-paid') }}" x-show="showPay" x-cloak
          class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:bg-emerald-500/10 dark:border-emerald-500/30 flex flex-wrap items-end gap-3">
        @csrf
        <template x-for="id in selected" :key="id"><input type="hidden" name="record_ids[]" :value="id"></template>
        <div>
            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Payment date</label>
            <input type="date" name="payment_date" required value="{{ now()->toDateString() }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Reference <span class="normal-case font-medium">(optional)</span></label>
            <input type="text" name="payment_reference" maxlength="120" placeholder="e.g. payroll run / bank ref" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-700">Mark <span x-text="selected.length"></span> paid</button>
        <button type="button" @click="showPay = false" class="text-xs font-bold text-slate-500">Cancel</button>
    </form>

    {{-- Records --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-left font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-900/40">
                    <th class="px-4 py-2.5"></th><th class="px-4 py-2.5">Employee</th><th class="px-4 py-2.5">Policy · Year</th>
                    <th class="px-4 py-2.5 text-right">Remaining</th><th class="px-4 py-2.5 text-right">Cap</th>
                    <th class="px-4 py-2.5 text-right">Encashed</th><th class="px-4 py-2.5 text-right">Lapsed</th>
                    <th class="px-4 py-2.5 text-right">Amount</th><th class="px-4 py-2.5">Rule</th>
                    <th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5 text-right">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($records as $r)
                        <tr x-data="{ showReject: false, open: false }" :class="open ? 'bg-slate-50/60 dark:bg-slate-900/30' : ''">
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    @if(in_array($r->status, ['pending', 'approved'], true))
                                        <input type="checkbox" :checked="selected.includes({{ $r->id }})"
                                               @change="selected.includes({{ $r->id }}) ? selected = selected.filter(i => i !== {{ $r->id }}) : selected.push({{ $r->id }})"
                                               class="rounded border-slate-300 text-brand-600">
                                    @endif
                                    <button type="button" @click="open = !open" :aria-expanded="open" title="Full details"
                                            class="rounded-md p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700">
                                        <i data-lucide="chevron-down" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ optional($r->employee)->full_name }}</span>
                                <span class="block text-[10px] text-slate-400">{{ optional(optional($r->employee)->department)->name }}</span>
                            </td>
                            <td class="px-4 py-2.5">{{ optional($r->policy)->name }}<span class="block text-[10px] text-slate-400">{{ $r->leave_year_label }}@if($r->is_pro_rata) · pro-rata {{ $r->pro_rata_months }}mo @endif</span></td>
                            <td class="px-4 py-2.5 text-right">{{ $fmtD($r->days_remaining_before_renewal) }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $fmtD($r->encashment_cap_days) }}</td>
                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600">{{ $fmtD($r->days_to_encash) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $r->days_lapsed > 0 ? 'font-bold text-amber-600' : 'text-slate-400' }}">{{ $fmtD($r->days_lapsed) }}</td>
                            <td class="px-4 py-2.5 text-right font-extrabold text-slate-800 dark:text-white whitespace-nowrap">{{ $r->formatted_amount }}</td>
                            <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap">{{ $r->encashment_type === 'percent_of_annual' ? $fmtD($r->encashment_value) . '% of annual' : str_replace('_', ' ', $r->encashment_type) }}</td>
                            <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $r->status_badge_color }}">{{ ucfirst($r->status) }}</span>
                                @if($r->status === 'paid' && $r->payment_date)<span class="block text-[10px] text-slate-400 mt-0.5">{{ $r->payment_date->format('d M Y') }}{{ $r->payment_reference ? ' · ' . $r->payment_reference : '' }}</span>@endif
                                @if($r->status === 'rejected' && $r->admin_notes)<span class="block text-[10px] text-rose-500 mt-0.5 max-w-[160px] truncate" title="{{ $r->admin_notes }}">{{ $r->admin_notes }}</span>@endif
                            </td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                @if($r->status === 'pending')
                                    <form method="POST" action="{{ route('leave-encashments.approve', $r) }}" class="inline">@csrf
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-emerald-700">Approve</button>
                                    </form>
                                @endif
                                @if(in_array($r->status, ['pending', 'approved'], true))
                                    <button type="button" @click="showReject = !showReject" class="rounded-lg bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-100 dark:bg-rose-500/10">Reject</button>
                                    <form method="POST" action="{{ route('leave-encashments.reject', $r) }}" x-show="showReject" x-cloak class="mt-1.5 flex gap-1">@csrf
                                        <input type="text" name="admin_notes" required placeholder="Reason" class="w-36 rounded-lg border border-rose-200 px-2 py-1 text-[11px] dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                                        <button type="submit" class="rounded-lg bg-rose-600 px-2 py-1 text-[11px] font-bold text-white">✓</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        {{-- Full record breakdown --}}
                        <tr x-show="open" x-cloak>
                            <td colspan="11" class="px-4 pb-4 pt-0 bg-slate-50/60 dark:bg-slate-900/30">
                                @php
                                    $cur = $r->currency;
                                    $money = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
                                    $rule = $r->encashment_type === 'percent_of_annual'
                                        ? $fmtD($r->encashment_value) . '% of annual allocation'
                                        : ucfirst(str_replace('_', ' ', (string) $r->encashment_type));
                                @endphp
                                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-3">Encashment breakdown — {{ optional($r->employee)->full_name }} · {{ optional($r->policy)->name }} · {{ $r->leave_year_label }}</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-3 text-xs">
                                        <div><dt class="text-slate-400">Renewal year</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $r->renewal_year }}</dd></div>
                                        <div><dt class="text-slate-400">Annual allocation</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $fmtD($r->annual_allocation) }} days</dd></div>
                                        <div><dt class="text-slate-400">Pro-rata</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $r->is_pro_rata ? 'Yes · ' . $r->pro_rata_months . ' month(s)' : 'No (full year)' }}</dd></div>
                                        <div><dt class="text-slate-400">Days remaining</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $fmtD($r->days_remaining_before_renewal) }}</dd></div>
                                        <div><dt class="text-slate-400">Encashment rule</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $rule }}</dd></div>
                                        <div><dt class="text-slate-400">Cap</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $fmtD($r->encashment_cap_days) }} days</dd></div>
                                        <div><dt class="text-slate-400">Days encashed</dt><dd class="font-semibold text-emerald-600">{{ $fmtD($r->days_to_encash) }}</dd></div>
                                        <div><dt class="text-slate-400">Days lapsed</dt><dd class="font-semibold {{ $r->days_lapsed > 0 ? 'text-amber-600' : 'text-slate-500' }}">{{ $fmtD($r->days_lapsed) }}</dd></div>
                                        <div><dt class="text-slate-400">Monthly salary (snapshot)</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $money($r->monthly_salary_snapshot) }}</dd></div>
                                        <div><dt class="text-slate-400">Daily rate</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $money($r->daily_rate) }}</dd></div>
                                        <div><dt class="text-slate-400">Amount</dt><dd class="font-extrabold text-slate-900 dark:text-white">{{ $r->formatted_amount }}</dd></div>
                                        <div><dt class="text-slate-400">Status</dt><dd><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $r->status_badge_color }}">{{ ucfirst($r->status) }}</span></dd></div>
                                        <div><dt class="text-slate-400">Processed by</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ optional($r->processedBy)->full_name ?? '—' }}</dd></div>
                                        <div><dt class="text-slate-400">Processed at</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ optional($r->processed_at)->format('d M Y H:i') ?? '—' }}</dd></div>
                                        <div><dt class="text-slate-400">Payment date</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ optional($r->payment_date)->format('d M Y') ?? '—' }}</dd></div>
                                        <div><dt class="text-slate-400">Payment reference</dt><dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $r->payment_reference ?: '—' }}</dd></div>
                                        @if($r->admin_notes)
                                            <div class="col-span-2 sm:col-span-3 lg:col-span-4"><dt class="text-slate-400">Admin notes</dt><dd class="font-medium text-slate-700 dark:text-slate-300">{{ $r->admin_notes }}</dd></div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-12 text-center text-slate-400">No encashment records for this year.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
