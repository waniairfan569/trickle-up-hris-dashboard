@extends('layouts.hr-app')

@section('title', 'Form Responses')
@section('breadcrumb', 'Form Responses')

@php
    // Status pill links preserve the current form + search filters.
    $pillUrl = fn ($s) => route('company-forms.inbox', array_filter([
        'status' => $s, 'form' => $formId, 'q' => $search,
    ], fn ($v) => $v !== null && $v !== ''));
    $pill = fn ($active) => 'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition '
        . ($active ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700');
    $badge = fn ($count, $active) => '<span class="rounded-full px-1.5 min-w-[1.25rem] text-center '
        . ($active ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-700') . '">' . $count . '</span>';
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-5xl mx-auto space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="inbox" class="h-6 w-6 text-brand-500"></i> Form Responses
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review, comment on, and approve or reject submissions from every form — all in one place.</p>
        </div>
        <a href="{{ route('company-forms.index') }}" class="inline-flex items-center gap-1.5 self-start rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
            <i data-lucide="clipboard-list" class="h-3.5 w-3.5"></i> All forms
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $pillUrl('awaiting') }}" class="{{ $pill($status === 'awaiting') }}">Awaiting {!! $badge($counts['awaiting'], $status === 'awaiting') !!}</a>
            <a href="{{ $pillUrl('approved') }}" class="{{ $pill($status === 'approved') }}">Approved {!! $badge($counts['approved'], $status === 'approved') !!}</a>
            <a href="{{ $pillUrl('rejected') }}" class="{{ $pill($status === 'rejected') }}">Rejected {!! $badge($counts['rejected'], $status === 'rejected') !!}</a>
            <a href="{{ $pillUrl('all') }}" class="{{ $pill($status === 'all') }}">All</a>
        </div>
        <form method="GET" action="{{ route('company-forms.inbox') }}" class="flex flex-wrap items-center gap-2 sm:ml-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="form" onchange="this.form.submit()" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-200">
                <option value="">All forms</option>
                @foreach($forms as $f)<option value="{{ $f->id }}" @selected((string) $formId === (string) $f->id)>{{ $f->title }}</option>@endforeach
            </select>
            <div class="relative">
                <i data-lucide="search" class="h-3.5 w-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search employee…" class="rounded-xl border border-slate-300 bg-white pl-8 pr-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-200 w-44">
            </div>
            @if($search || $formId)
                <a href="{{ route('company-forms.inbox', ['status' => $status]) }}" class="text-xs font-semibold text-slate-400 hover:text-rose-600"><i data-lucide="x" class="h-3.5 w-3.5 inline"></i> Clear</a>
            @endif
        </form>
    </div>

    {{-- Submissions --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700/60">
        @forelse($submissions as $s)
            @php
                $anon = optional($s->form)->is_anonymous;
                $emp = $s->employee;
                $name = $anon ? 'Anonymous' : (optional($emp)->full_name ?? trim(optional($emp)->first_name.' '.optional($emp)->last_name) ?: '—');
                $initials = $anon ? '?' : strtoupper(substr((string) optional($emp)->first_name, 0, 1).substr((string) optional($emp)->last_name, 0, 1));
                $reviewed = in_array($s->review_status, ['approved', 'rejected'], true);
                $preview = $s->responses->filter(fn ($r) => $r->value !== null && $r->value !== '' && !$r->isFile() && !str_starts_with((string) $r->value, 'data:image'))
                    ->take(3)->map(fn ($r) => $r->getDisplayValue())->implode(' · ');
            @endphp
            <div x-data="{ open: false }">
                <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50/60 dark:hover:bg-slate-700/20 transition">
                    <span class="h-9 w-9 shrink-0 rounded-full grid place-items-center text-white text-[11px] font-bold {{ $anon ? 'bg-slate-400' : 'bg-gradient-to-br from-brand-400 to-indigo-500' }}">
                        @if(!$anon && optional($emp)->avatar_url)<img src="{{ $emp->avatar_url }}" class="h-full w-full rounded-full object-cover">@else{{ $initials }}@endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $name }}</p>
                            <span class="text-[11px] font-semibold text-slate-400 shrink-0">· {{ optional($s->form)->title }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 truncate">
                            {{ optional($s->submitted_at)->format('M d, Y · g:i A') }}@if($s->period) · {{ $s->periodLabel() }} @endif
                            @if($preview) — <span class="text-slate-500 dark:text-slate-300">{{ $preview }}</span>@endif
                        </p>
                    </div>
                    <span class="shrink-0 text-[11px] font-bold rounded-full px-2.5 py-0.5 {{ $s->reviewBadgeClass() }}">{{ $s->reviewLabel() }}</span>
                    <button type="button" @click="open = true" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                        <i data-lucide="{{ $reviewed ? 'eye' : 'gavel' }}" class="h-3.5 w-3.5"></i> {{ $reviewed ? 'View' : 'Review' }}
                    </button>
                </div>

                {{-- Slide-over review panel --}}
                <div x-show="open" x-cloak class="fixed inset-0 z-50" style="display:none;">
                    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open = false"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
                    <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-white dark:bg-slate-800 shadow-2xl flex flex-col"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
                        {{-- head --}}
                        <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                            <div class="min-w-0">
                                <h3 class="text-base font-extrabold text-slate-900 dark:text-white truncate">{{ optional($s->form)->title }}</h3>
                                <p class="text-xs text-slate-400 mt-0.5 truncate">{{ $name }} · {{ optional($s->submitted_at)->format('M d, Y · g:i A') }}@if($s->period) · {{ $s->periodLabel() }} @endif</p>
                            </div>
                            <button type="button" @click="open = false" class="shrink-0 text-slate-400 hover:text-slate-600"><i data-lucide="x" class="h-5 w-5"></i></button>
                        </div>

                        {{-- answers + outcome --}}
                        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
                            @include('company-forms._answers', ['submission' => $s])

                            @if($reviewed)
                                @php $approved = $s->review_status === 'approved'; @endphp
                                <div class="rounded-xl border p-4 {{ $approved ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/5' : 'border-rose-200 bg-rose-50/50 dark:border-rose-500/20 dark:bg-rose-500/5' }}">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="{{ $approved ? 'check-circle' : 'x-circle' }}" class="h-4 w-4 {{ $approved ? 'text-emerald-600' : 'text-rose-600' }}"></i>
                                        <span class="text-sm font-extrabold {{ $approved ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300' }}">{{ $s->reviewLabel() }}</span>
                                        <span class="text-[11px] text-slate-400 ml-auto">{{ optional($s->reviewer)->full_name ?? 'HR' }} · {{ optional($s->reviewed_at)->format('M d, Y · g:i A') }}</span>
                                    </div>
                                    @if($s->review_note)<p class="text-sm text-slate-700 dark:text-slate-300 mt-2"><span class="font-bold">Comment:</span> {{ $s->review_note }}</p>@endif
                                </div>
                            @endif
                        </div>

                        {{-- action bar --}}
                        <div class="border-t border-slate-100 dark:border-slate-700/60 px-6 py-4">
                            <form method="POST" action="{{ route('company-forms.submission.review', $s) }}" class="space-y-3">
                                @csrf
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Comment to employee <span class="font-normal text-slate-300 normal-case">(optional)</span></label>
                                <textarea name="review_note" rows="2" maxlength="2000" placeholder="Add a note the employee will see…" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none">{{ $s->review_note }}</textarea>
                                <div class="flex items-center justify-end gap-2">
                                    <button type="submit" name="action" value="reject" onclick="return confirm('Reject this response and notify the employee?')" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700"><i data-lucide="x" class="h-4 w-4"></i> Reject</button>
                                    <button type="submit" name="action" value="approve" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"><i data-lucide="check" class="h-4 w-4"></i> {{ $reviewed ? 'Update to approved' : 'Approve' }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <i data-lucide="inbox" class="h-10 w-10 text-slate-300 mx-auto mb-3"></i>
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
                    @if($status === 'awaiting') Nothing awaiting review. 🎉 @else No {{ $status === 'all' ? '' : $status }} responses{{ $search || $formId ? ' match your filters' : '' }}. @endif
                </p>
            </div>
        @endforelse
    </div>

    @if($submissions->hasPages())
        <div>{{ $submissions->links() }}</div>
    @endif
</div>
<script>window.lucide && lucide.createIcons();</script>
@endsection
