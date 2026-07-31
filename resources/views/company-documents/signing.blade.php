@extends('layouts.hr-app')

@section('title', 'Signing status · ' . $document->title)
@section('breadcrumb', 'Company Documents')

@section('content')
@php
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'amber'   => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'rose'    => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'slate'   => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
@endphp
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <a href="{{ route('company-documents.admin') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All documents</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white mt-1 flex items-center gap-2">
            <i data-lucide="file-signature" class="h-6 w-6 text-brand-500"></i> {{ $document->title }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Who has signed and who is still pending.</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Sent to</p>
            <p class="text-3xl font-extrabold text-slate-800 dark:text-white mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Signed</p>
            <p class="text-3xl font-extrabold text-emerald-600 mt-1">{{ $stats['signed'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pending</p>
            <p class="text-3xl font-extrabold text-amber-600 mt-1">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Declined</p>
            <p class="text-3xl font-extrabold text-rose-600 mt-1">{{ $stats['declined'] }}</p>
        </div>
    </div>

    <!-- Recipients -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                    <th class="px-5 py-3">Recipient</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Signers</th>
                    <th class="px-5 py-3">Sent</th>
                    <th class="px-5 py-3 text-right"></th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-indigo-500 text-xs font-bold text-white">{{ optional($row['subject'])->initials ?? '—' }}</div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 dark:text-white truncate">{{ optional($row['subject'])->full_name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-slate-400 truncate">{{ optional(optional($row['subject'])->department)->name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $tones[$row['tone']] }}">{{ $row['label'] }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col gap-1">
                                    @foreach($row['request']->signers->sortBy('position') as $s)
                                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                                            @if($s->status === 'signed')
                                                <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-emerald-500"></i>
                                            @elseif($s->status === 'declined')
                                                <i data-lucide="x-circle" class="h-3.5 w-3.5 text-rose-500"></i>
                                            @else
                                                <i data-lucide="clock" class="h-3.5 w-3.5 text-amber-500"></i>
                                            @endif
                                            <span class="truncate">{{ optional($s->user)->full_name ?? $s->role_label ?? 'Signer' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ optional($row['sent_at'])->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('documents.show', $row['request']) }}" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700">View <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400">This document hasn't been sent for signature yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
