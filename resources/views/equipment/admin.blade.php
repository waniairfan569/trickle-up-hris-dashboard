@extends('layouts.hr-app')

@section('title', 'Equipment Requests')
@section('breadcrumb', 'Equipment Requests')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-4xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="package" class="h-6 w-6 text-brand-500"></i> Equipment Requests
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Employees asking to take company equipment home. Approve or decline — they’re notified by email.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif

    {{-- Pending --}}
    <div class="space-y-3">
        @forelse($pending as $req)
            <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 dark:bg-slate-800 dark:border-amber-500/30" x-data="{ showReject: false }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-900 dark:text-white">
                            {{ optional($req->employee)->full_name ?? 'Employee' }} wants to take <span class="text-brand-600 dark:text-brand-400">{{ $req->equipment_name }}</span> home
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $req->request_number }} · requested {{ $req->created_at->diffForHumans() }}@if($req->expected_return_date) · return by {{ $req->expected_return_date->format('d M Y') }}@endif</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2"><span class="font-bold text-slate-600 dark:text-slate-300">Reason:</span> {{ $req->reason }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-500/10 shrink-0">⏳ Pending</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('equipment.approve', $req->id) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                            <i data-lucide="check" class="h-4 w-4"></i> Approve
                        </button>
                    </form>
                    <button type="button" @click="showReject = !showReject"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400">
                        <i data-lucide="x" class="h-4 w-4"></i> Decline
                    </button>
                </div>

                {{-- Decline reason (revealed) --}}
                <form method="POST" action="{{ route('equipment.reject', $req->id) }}" x-show="showReject" x-cloak x-transition class="mt-2 flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text" name="review_note" maxlength="255" placeholder="Reason (optional — shown to the employee)"
                           class="flex-1 rounded-xl border border-rose-200 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700">
                        <i data-lucide="ban" class="h-4 w-4"></i> Confirm decline
                    </button>
                </form>

                {{-- Approve with optional note --}}
                <details class="mt-2 group">
                    <summary class="text-[11px] font-bold text-slate-400 cursor-pointer hover:text-slate-600 dark:hover:text-slate-300">Add a note when approving?</summary>
                    <form method="POST" action="{{ route('equipment.approve', $req->id) }}" class="mt-2 flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input type="text" name="review_note" maxlength="255" placeholder="Note (optional — shown to the employee)"
                               class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                            <i data-lucide="check" class="h-4 w-4"></i> Approve with note
                        </button>
                    </form>
                </details>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="check-check" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">All caught up</p>
                <p class="text-xs text-slate-400 mt-1">No equipment requests waiting for review.</p>
            </div>
        @endforelse
    </div>

    {{-- Decided (collapsible) --}}
    @if($decided->count())
        <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                <span>Recently decided ({{ $decided->count() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" x-cloak class="divide-y divide-slate-100 dark:divide-slate-700/60 border-t border-slate-100 dark:border-slate-700/60">
                @foreach($decided as $req)
                    <div class="px-5 py-3 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ optional($req->employee)->full_name ?? 'Employee' }} · {{ $req->equipment_name }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 font-bold {{ $req->status === 'approved' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' }}">{{ ucfirst($req->status) }}</span>
                        </div>
                        <p class="text-slate-400 mt-0.5">{{ $req->reviewed_at ? $req->reviewed_at->diffForHumans() : '' }} · by {{ optional($req->reviewer)->full_name ?? 'Admin' }}@if($req->review_note) · “{{ $req->review_note }}”@endif</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
