@extends('layouts.hr-app')

@section('title', 'Responses · ' . $form->title)
@section('breadcrumb', 'Company Forms')

@php
    $badge = ['pending' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300', 'in_progress' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', 'submitted' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'];
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('company-forms.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All forms</a>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white mt-1">{{ $form->title }} — Responses</h1>
        </div>
        <a href="{{ route('company-forms.export', $form) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="download" class="h-4 w-4"></i> Export to Excel</a>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach([['Assigned', $stats['assigned'], 'text-slate-700'], ['Submitted', $stats['submitted'], 'text-emerald-600'], ['Pending', $stats['pending'], 'text-amber-600'], ['Overdue', $stats['overdue'], 'text-rose-600']] as [$label, $val, $color])
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                <p class="text-3xl font-extrabold {{ $color }} dark:text-white mt-1">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                    <th class="px-5 py-3">Employee</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Submitted</th><th class="px-5 py-3"></th>
                </tr></thead>
                <tbody>
                    @forelse($submissions as $s)
                        <tr class="border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <td class="px-5 py-2.5 font-semibold text-slate-800 dark:text-white">{{ $form->is_anonymous ? 'Anonymous' : (optional($s->employee)->full_name ?? '—') }}</td>
                            <td class="px-5 py-2.5 text-slate-500">{{ optional(optional($s->employee)->department)->name ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge[$s->status] }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span>
                                @if(in_array($s->review_status, ['approved', 'rejected'], true))
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ml-1 {{ $s->reviewBadgeClass() }}">{{ $s->reviewLabel() }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ optional($s->submitted_at)->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-right">@if($s->status === 'submitted')<a href="{{ route('company-forms.submission', $s) }}" class="text-xs font-bold text-brand-600 hover:underline">View response</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400">No submissions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
