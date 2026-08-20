@extends('layouts.hr-app')

@section('title', 'Company WFH Days')
@section('breadcrumb', 'Company WFH Days')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="house-wifi" class="h-6 w-6 text-brand-500"></i> Company WFH Days
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Dates when the whole company works from home. Every employee automatically clocks in via the dashboard on these days.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Add --}}
    <form method="POST" action="{{ route('company-wfh-days.store') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
        @csrf
        <div class="grid gap-4 sm:grid-cols-[auto_1fr_auto] items-end">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Date</label>
                <input type="date" name="date" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Note (optional)</label>
                <input type="text" name="note" placeholder="e.g. Monthly remote Friday" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
            </div>
            <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition">
                <i data-lucide="plus" class="h-4 w-4"></i> Add WFH day
            </button>
        </div>
    </form>

    {{-- Upcoming --}}
    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Upcoming</h2>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($upcoming as $day)
                <div class="px-5 py-3 flex items-center justify-between gap-4">
                    <div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-white">{{ $day->date->format('l, d M Y') }}</div>
                        @if($day->note)<div class="text-xs text-slate-400">{{ $day->note }}</div>@endif
                    </div>
                    <form method="POST" action="{{ route('company-wfh-days.destroy', $day) }}" onsubmit="return confirm('Remove this company WFH day?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition dark:hover:bg-rose-500/10" title="Remove"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </form>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-400">No upcoming company WFH days.</div>
            @endforelse
        </div>
    </section>

    {{-- Past --}}
    @if($past->isNotEmpty())
        <section>
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Past</h2>
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($past as $day)
                    <div class="px-5 py-2.5 flex items-center justify-between gap-4 text-sm text-slate-500">
                        <span>{{ $day->date->format('l, d M Y') }} @if($day->note)<span class="text-xs text-slate-400">· {{ $day->note }}</span>@endif</span>
                        <form method="POST" action="{{ route('company-wfh-days.destroy', $day) }}" onsubmit="return confirm('Remove this company WFH day?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg p-1.5 text-slate-300 hover:text-rose-600" title="Remove"><i data-lucide="x" class="h-4 w-4"></i></button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
