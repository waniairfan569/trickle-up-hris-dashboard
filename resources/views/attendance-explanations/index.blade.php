@extends('layouts.hr-app')

@section('title', 'My Lateness')
@section('breadcrumb', 'My Lateness')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="alarm-clock" class="h-6 w-6 text-amber-500"></i> My lateness
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Your own account of each late day. These go onto your month-end review, so you are
            never asked for the same reason twice.
        </p>
    </div>

    {{-- Flash messages come from the layout; repeating them here shows each twice. --}}

    {{-- Still to answer --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Needs a reason</h2>
            <p class="text-xs text-slate-400 mt-0.5">Days left blank show as “No reason provided” on your review.</p>
        </div>

        @if($outstanding->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-slate-400">Nothing outstanding — every late day is explained.</div>
        @else
            <form method="POST" action="{{ route('attendance-explanations.store') }}" class="px-6 py-5 space-y-4">
                @csrf
                @foreach($outstanding as $day)
                    <div>
                        <label for="ex-{{ $day->id }}" class="mb-1.5 flex flex-wrap items-baseline gap-x-2">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $day->date->format('D, d M Y') }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $day->late_minutes }} min late</span>
                        </label>
                        <textarea id="ex-{{ $day->id }}" name="reasons[{{ $day->id }}]" rows="2" maxlength="500"
                                  placeholder="What happened?"
                                  class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
                    </div>
                @endforeach
                <div class="flex justify-end">
                    <button type="submit" class="btn-brand"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
                </div>
            </form>
        @endif
    </section>

    {{-- Already answered --}}
    <section class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Explained</h2>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($explained as $e)
                <div class="px-6 py-3.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $e->date->format('D, d M Y') }}</span>
                        <span class="text-xs text-slate-400">
                            {{ $e->late_minutes }} min late · answered {{ optional($e->acknowledged_at)->format('d M, g:i A') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 italic">“{{ $e->reason }}”</p>
                    @if($e->document)
                        <p class="mt-1 text-[11px] text-slate-400">
                            <i data-lucide="file-text" class="h-3 w-3 inline -mt-0.5"></i>
                            Included in {{ $e->document->template_name }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="px-6 py-8 text-center text-sm text-slate-400">You haven’t explained any late days yet.</div>
            @endforelse
        </div>
    </section>

</div>
@endsection
