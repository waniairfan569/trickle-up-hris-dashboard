@extends('layouts.hr-app')

@section('title', 'My Forms')
@section('breadcrumb', 'My Forms')

@php
    $badge = ['pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', 'in_progress' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400', 'submitted' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'];
    $label = ['pending' => 'Pending', 'in_progress' => 'In progress', 'submitted' => 'Submitted'];
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="{ tab: 'pending' }">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">My Forms</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Forms assigned to you to complete.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700">
        @foreach(['pending' => 'Pending', 'submitted' => 'Submitted', 'all' => 'All'] as $key => $lbl)
            <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'" class="border-b-2 px-4 py-2.5 text-sm font-bold transition">{{ $lbl }}</button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($submissions as $s)
            @php
                $isSubmitted = $s->status === 'submitted';
                $matchPending = !$isSubmitted;
            @endphp
            <div x-show="tab === 'all' || (tab === 'submitted' && {{ $isSubmitted ? 'true' : 'false' }}) || (tab === 'pending' && {{ $matchPending ? 'true' : 'false' }})"
                 class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $s->form->title }}</p>
                        @if($s->status === 'submitted')
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge['submitted'] }}">Submitted</span>
                        @endif
                        @if(in_array($s->review_status, ['approved', 'rejected'], true))
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $s->reviewBadgeClass() }}">{{ $s->reviewLabel() }}</span>
                        @endif
                    </div>
                    @if($s->form->description)<p class="text-xs text-slate-400 mt-1 truncate">{{ Str::limit($s->form->description, 90) }}</p>@endif
                    @if($s->form->deadline)
                        <p class="text-xs mt-1 {{ $s->form->isOverdue() && !$isSubmitted ? 'text-rose-600 font-semibold' : 'text-slate-400' }}">
                            <i data-lucide="clock" class="h-3 w-3 inline -mt-0.5"></i> Due {{ $s->form->deadline->format('d M Y, H:i') }}{{ $s->form->isOverdue() && !$isSubmitted ? ' · overdue' : '' }}
                        </p>
                    @endif
                </div>
                @if($isSubmitted && !$s->form->allow_multiple_submissions)
                    <a href="{{ route('forms.fill', $s->form) }}" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 shrink-0">View submission</a>
                @else
                    <a href="{{ route('forms.fill', $s->form) }}" class="rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-brand-700 shrink-0">{{ $isSubmitted ? 'Submit again' : 'Fill form' }}</a>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="clipboard-check" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No forms assigned</p>
                <p class="text-xs text-slate-400 mt-1">Forms assigned to you will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
