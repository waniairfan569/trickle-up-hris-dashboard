@extends('layouts.operator')

@section('title', 'Companies')
@section('breadcrumb', 'Companies')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="building-2" class="h-6 w-6 text-indigo-500"></i> Companies
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Every company (agency) using the platform — manage plans, status and access.</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach([
            ['Agencies', $stats['total'], 'building-2'],
            ['Active', $stats['active'], 'check-circle'],
            ['Trialing', $stats['trialing'], 'clock'],
            ['Suspended', $stats['suspended'], 'ban'],
            ['MRR', $symbol . number_format($stats['mrr']), 'trending-up'],
        ] as [$label, $val, $icon])
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-2 text-slate-400"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i><span class="text-[11px] font-bold uppercase tracking-wider">{{ $label }}</span></div>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <!-- Tenants -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">All agencies ({{ $tenants->count() }})</h2></div>
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr class="text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">
                    <th class="px-5 py-3">Agency</th>
                    <th class="px-5 py-3">Admin</th>
                    <th class="px-5 py-3">Plan</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Seats</th>
                    <th class="px-5 py-3">Joined</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($tenants as $t)
                    @php
                        $badge = ['active'=>'bg-emerald-50 text-emerald-700','trialing'=>'bg-brand-50 text-brand-700','suspended'=>'bg-rose-50 text-rose-700'][$t->status] ?? 'bg-slate-100 text-slate-600';
                    @endphp
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                        <td class="px-5 py-3">
                            <a href="{{ route('operator.companies.show', $t) }}" class="font-bold text-slate-800 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $t->brand_name ?: $t->name }}</a>
                            <div class="text-[11px] text-slate-400">{{ $t->slug }}@if($t->discount_percent) · <span class="text-emerald-600 font-semibold">-{{ $t->discount_percent }}%</span>@endif</div>
                        </td>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                            {{ optional($t->admin)->full_name ?? '—' }}
                            <div class="text-[11px] text-slate-400">{{ optional($t->admin)->email }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <form action="{{ route('operator.plan', $t) }}" method="POST" class="inline">
                                @csrf
                                <select name="plan" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs py-1 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    @foreach($plans as $p)
                                        <option value="{{ $p->key }}" @selected($t->planKey()===$p->key)>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-bold {{ $badge }} dark:bg-opacity-20">{{ ucfirst($t->status) }}</span>
                            @if($t->status==='trialing' && $t->trial_ends_at)<div class="text-[11px] text-slate-400 mt-0.5">{{ $t->trialDaysLeft() }}d left</div>@endif
                        </td>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $t->seat_count }}<span class="text-slate-400"> / {{ $t->seatLimit()===0 ? '∞' : $t->seatLimit() }}</span></td>
                        <td class="px-5 py-3 text-slate-500">{{ optional($t->created_at)->format('d M Y') }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('operator.companies.show', $t) }}" title="Manage" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-700"><i data-lucide="settings-2" class="h-4 w-4"></i></a>
                                @if($t->admin)
                                    <form action="{{ route('operator.impersonate', $t) }}" method="POST" onsubmit="return confirm('Log in as {{ $t->name }}\'s admin? You can return to the console anytime.');">
                                        @csrf
                                        <button title="Impersonate" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="log-in" class="h-4 w-4"></i></button>
                                    </form>
                                @endif
                                @if($t->status==='suspended')
                                    <form action="{{ route('operator.activate', $t) }}" method="POST">@csrf<button title="Activate" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10"><i data-lucide="play" class="h-4 w-4"></i></button></form>
                                @else
                                    <form action="{{ route('operator.suspend', $t) }}" method="POST" onsubmit="return confirm('Suspend {{ $t->name }}? Their users will be locked out.');">@csrf<button title="Suspend" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="ban" class="h-4 w-4"></i></button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
