@extends('layouts.hr-app')

@section('title', 'Checks')
@section('breadcrumb', 'Checks')

@php
    $badge = [
        'valid'    => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'expiring' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'expired'  => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'pending'  => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'failed'   => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
    ];
    $reads = [
        'valid' => 'Valid', 'expiring' => 'Expiring soon', 'expired' => 'Expired',
        'pending' => 'Waiting', 'failed' => 'Not passed',
    ];
@endphp

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Checks</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Right to work, DBS and the rest — the ones that have lapsed, are about to, or are still waiting.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @foreach(['attention' => 'Needs attention', 'all' => 'All checks'] as $key => $label)
            <a href="{{ route('employee-checks.index', ['state' => $key]) }}"
               class="inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-bold transition
                      {{ $state === $key
                         ? 'border-brand-400 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300'
                         : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700/40' }}">
                {{ $label }}
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700/60">
                <thead class="bg-slate-50 dark:bg-slate-700/30">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="px-5 py-3">Who</th>
                        <th class="px-5 py-3">Check</th>
                        <th class="px-5 py-3">Outcome</th>
                        <th class="px-5 py-3">Valid until</th>
                        <th class="px-5 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($checks as $check)
                        @php $state = $check->state(); @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('employee-checks.employee', $check->user_id) }}"
                                   class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">
                                    {{ $check->employee?->full_name ?? '—' }}
                                </a>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-200">{{ $check->typeLabel() }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $badge[$state] }}">{{ $reads[$state] }}</span>
                            </td>
                            <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                                {{ $check->expires_on?->format('j M Y') ?? 'Does not expire' }}
                                @if($state === 'expiring')
                                    <span class="block text-[11px] text-amber-600 dark:text-amber-400">in {{ (int) now()->diffInDays($check->expires_on) }} day(s)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('employee-checks.employee', $check->user_id) }}"
                                   class="text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400">
                                @if($state === 'attention')
                                    Nothing needs attention. Checks appear here when they lapse, are within a month
                                    of lapsing, or are still waiting on an outcome.
                                @else
                                    No checks recorded yet. Open an employee and record one.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $checks->links() }}

    <div class="text-xs text-slate-400">
        No checking service is connected. These are the checks your own people carried out, recorded so they
        can be shown later and chased before they lapse.
    </div>
</div>
@endsection
