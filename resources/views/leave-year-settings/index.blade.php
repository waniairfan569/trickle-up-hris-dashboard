@extends('layouts.hr-app')

@section('title', 'Leave Year & Encashment')
@section('breadcrumb', 'Leave Year & Encashment')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="calendar-sync" class="h-6 w-6 text-brand-500"></i> Leave Year &amp; Encashment
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Per-policy leave year windows, year-end encashment rules, pro-rata for joiners and automatic renewal.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('time-off-policies.balances-overview') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="table-2" class="h-4 w-4"></i> Leave Balances</a>
            <a href="{{ route('leave-year-settings.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700"><i data-lucide="plus" class="h-4 w-4"></i> New setting</a>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 flex items-center gap-2"><i data-lucide="alert-circle" class="h-5 w-5"></i>{{ session('error') }}</div>@endif

    @if($due->count())
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300 flex items-start gap-2">
            <i data-lucide="alarm-clock" class="h-5 w-5 shrink-0"></i>
            <div><b>{{ $due->count() }} renewal(s) due:</b> {{ $due->map(fn ($s) => optional($s->policy)->name)->implode(', ') }} — preview and run below, or let the 1&nbsp;AM scheduler handle it.</div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($settings as $s)
            @php $annual = (float) optional($s->policy)->days_per_year; @endphp
            <div class="bg-white rounded-2xl border {{ $s->isDueForRenewal() && $s->is_active ? 'border-amber-300 dark:border-amber-500/40' : 'border-slate-200/80 dark:border-slate-700' }} shadow-sm p-5 dark:bg-slate-800">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-900 dark:text-white truncate">🏖 {{ optional($s->policy)->name ?? 'Policy' }}@if($s->entity) — {{ $s->entity->name }}@endif</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $s->name }}</p>
                    </div>
                    @unless($s->is_active)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700 shrink-0">Inactive</span>@endunless
                </div>

                <div class="mt-3 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                    <p><span class="font-bold text-slate-500">Year:</span> {{ \Carbon\Carbon::create(null, $s->year_start_month, 1)->format('F') }} {{ $s->year_start_day }} → {{ \Carbon\Carbon::create(null, $s->year_start_month, 1)->subMonth()->format('F') }} ({{ $s->getCurrentYearLabel() }})</p>
                    <p><span class="font-bold text-slate-500">Next renewal:</span> {{ optional($s->next_renewal_date)->format('M j, Y') }}
                        @if($s->next_renewal_date)
                            @if($s->isDueForRenewal())<span class="font-bold text-amber-600">— due now</span>
                            @else <span class="text-slate-400">(in {{ (int) today()->diffInDays($s->next_renewal_date) }} days)</span>@endif
                        @endif
                    </p>
                    <p><span class="font-bold text-slate-500">Encashment:</span> {{ $s->encashmentRuleLabel() }}</p>
                    <p><span class="font-bold text-slate-500">Pro-rata:</span> {{ $s->pro_rata_enabled ? '✅ Enabled (cutoff day ' . $s->pro_rata_cutoff_day . ', round ' . $s->pro_rata_round_to . ')' : '❌ Disabled' }}</p>
                    <p><span class="font-bold text-slate-500">Carry forward:</span> {{ $s->carry_forward_enabled ? '✅ ' . ($s->carry_forward_max_days ? 'max ' . rtrim(rtrim(number_format($s->carry_forward_max_days, 1), '0'), '.') . ' days' : 'unlimited') : '❌ Disabled' }}</p>
                    <p><span class="font-bold text-slate-500">Auto renewal:</span> {{ $s->auto_renewal_enabled ? '✅ 1 AM daily check' : '❌ Manual only' }}@if($s->last_renewal_date) · last run {{ $s->last_renewal_date->format('M j, Y') }}@endif</p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('leave-year-settings.preview', $s) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="eye" class="h-3.5 w-3.5"></i> Preview renewal</a>
                    <a href="{{ route('leave-year-settings.edit', $s) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</a>
                    <form method="POST" action="{{ route('leave-year-settings.destroy', $s) }}" onsubmit="return confirm('Delete this setting?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-700"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="calendar-sync" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No leave-year settings yet</p>
                <p class="text-xs text-slate-400 mt-1">Create one per policy to enable year-end encashment, pro-rata and auto-renewal.</p>
            </div>
        @endforelse
    </div>

    @if($recentLogs->count())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Recent renewal runs</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-left font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                        <th class="px-5 py-2.5">Date</th><th class="px-5 py-2.5">Policy · Year</th><th class="px-5 py-2.5">Trigger</th><th class="px-5 py-2.5 text-right">Employees</th><th class="px-5 py-2.5 text-right">Encashed</th><th class="px-5 py-2.5 text-right">Amount</th><th class="px-5 py-2.5 text-right">Lapsed</th><th class="px-5 py-2.5">Status</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($recentLogs as $log)
                            <tr>
                                <td class="px-5 py-2.5 whitespace-nowrap font-semibold text-slate-700 dark:text-slate-200">{{ $log->renewal_date->format('M j, Y') }}</td>
                                <td class="px-5 py-2.5">{{ optional($log->policy)->name }} · {{ $log->leave_year_label }}</td>
                                <td class="px-5 py-2.5 capitalize text-slate-500">{{ $log->triggered_by }}</td>
                                <td class="px-5 py-2.5 text-right">{{ $log->total_employees }}</td>
                                <td class="px-5 py-2.5 text-right">{{ $log->employees_with_encashment }}</td>
                                <td class="px-5 py-2.5 text-right font-semibold">PKR {{ number_format($log->total_encashment_amount, 0) }}</td>
                                <td class="px-5 py-2.5 text-right text-amber-600">{{ rtrim(rtrim(number_format($log->total_days_lapsed, 1), '0'), '.') }}d</td>
                                <td class="px-5 py-2.5"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $log->status === 'completed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : ($log->status === 'failed' ? 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-amber-50 text-amber-700') }}">{{ ucfirst($log->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
