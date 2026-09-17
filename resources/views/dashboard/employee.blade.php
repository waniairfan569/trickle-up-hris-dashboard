@extends('layouts.hr-app')

@section('title', 'My Dashboard')
@section('breadcrumb', '')

@php
    $auth = auth()->user();
    $status = app(\App\Services\AttendanceService::class)->getTodayStatus($auth);
    $isClockedIn = ($status['clock_in'] ?? null) && !($status['clock_out'] ?? null);

    // Real "needs attention" signals for this employee (documents to sign + own pending leave).
    $signCount = \App\Models\DocumentRequest::where('status', 'in_progress')
        ->whereHas('signers', fn ($s) => $s->where('user_id', $auth->id)->where('status', 'pending'))
        ->with('signers')->get()->filter(fn ($r) => $r->isAwaiting($auth))->count();
    $pendingLeaveCount = \App\Models\TimeOffRequest::where('user_id', $auth->id)->where('status', 'pending')->count();
    $attention = $signCount + $pendingLeaveCount;

    // When the leave balances renew (earliest active leave-year setting).
    $resetDate = null;
    try {
        $rd = \App\Models\LeaveYearSetting::where('is_active', true)->whereNotNull('next_renewal_date')->orderBy('next_renewal_date')->value('next_renewal_date');
        $resetDate = $rd ? \Carbon\Carbon::parse($rd) : null;
    } catch (\Throwable $e) {}

    // A gentle daily nudge — stable for the whole day.
    $quotes = [
        'Small steps every day lead to big results.',
        'Progress, not perfection.',
        'Great things are built one day at a time.',
        'Focus on what matters most today.',
        'Consistency beats intensity.',
        'Make today count.',
        'A little progress each day adds up.',
    ];
    $quote = $quotes[now()->dayOfYear % count($quotes)];
@endphp

{{-- Greeting shown in the top bar (see layouts/hr-app header) --}}
@section('greeting')
    <div class="min-w-0 leading-tight">
        <p class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-white truncate"><span>👋</span> Hello {{ $auth->first_name }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $isClockedIn ? "You're clocked in" : 'Welcome back' }}@if($attention > 0) and <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $attention }}</span> {{ \Illuminate\Support\Str::plural('thing', $attention) }} need your attention today.@else — here's your day at a glance.@endif</p>
    </div>
@endsection

@section('content')
<div class="mx-auto pb-12">

    <!-- Unread-announcement bar + auto-popup -->
    @include('partials.announcement-alert')

    <!-- Employees waiting for a login code — only for people a super admin delegated code-sending to -->
    @if(auth()->user()->can_send_codes && plan_allows('code_requests'))
        @include('partials.code-request-hr-banner')
    @endif

    <div class="space-y-6">

    <!-- Day at a glance -->
    @include("dashboard.partials.day-at-a-glance")

    {{-- Quick request cards (functionality wired later) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
            $quickCards = [
                ['title' => 'Request Time Off',      'text' => 'Apply for leave from your policies.',        'icon' => 'calendar-plus', 'tone' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400', 'hue' => 'indigo', 'href' => route('time-off.create')],
                ['title' => 'Request Login Code',     'text' => 'Get a one-time code for a company tool.',    'icon' => 'key-round',     'tone' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', 'hue' => 'amber', 'event' => 'open-code-request'],
                ['title' => 'Feedback & Suggestions', 'text' => 'Share feedback or raise an issue with HR.', 'icon' => 'message-square-heart', 'tone' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400', 'hue' => 'rose', 'event' => 'open-feedback'],
            ];
        @endphp
        @foreach($quickCards as $card)
            @include('dashboard.partials.action-card')
        @endforeach
    </div>

    {{-- Bottom action cards (functionality wired later) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
            $dashCards = [
                ['title' => 'Equipment',          'text' => 'Request approval to take a company item home.', 'icon' => 'package',      'tone' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'hue' => 'emerald', 'href' => route('equipment.index')],
                ['title' => 'Overtime Request',   'text' => 'Submit overtime for approval.',               'icon' => 'alarm-clock',  'tone' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400', 'hue' => 'violet', 'href' => route('time-off.index', ['overtime' => 1])],
                ['title' => 'WFH Approval',        'text' => 'Request to work from home — approved per request.', 'icon' => 'house',   'tone' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400', 'hue' => 'sky', 'href' => route('time-off.create', ['policy' => 'wfh'])],
            ];
        @endphp
        @foreach($dashCards as $card)
            @include('dashboard.partials.action-card')
        @endforeach
    </div>

    </div>{{-- /space-y-6 --}}
    @include('partials.code-request-modal')
    @include('partials.feedback-modal')
</div>
@endsection
