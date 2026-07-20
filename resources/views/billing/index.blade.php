@extends('layouts.hr-app')

@section('title', 'Billing & Plans')
@section('breadcrumb', 'Billing')

@section('content')
@php
    $currentKey = $tenant->planKey();
    $seatLimit = $tenant->seatLimit();
    $seatCount = $tenant->seatCount();
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="credit-card" class="h-6 w-6 text-brand-500"></i> Billing &amp; Plans
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage your subscription and seats.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}
        </div>
    @endif

    <!-- Current status -->
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Current plan</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $tenant->planConfig()['name'] ?? ucfirst($currentKey) }}</p>
                @if($tenant->onTrial())
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-bold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300 mt-1">
                        <i data-lucide="clock" class="h-3.5 w-3.5"></i> Trial · {{ $tenant->trialDaysLeft() }} day(s) left
                    </span>
                @elseif($tenant->trialExpired())
                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-500/10 dark:text-rose-300 mt-1">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i> Trial ended — choose a plan
                    </span>
                @elseif($tenant->status === 'active')
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300 mt-1">
                        <i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Active
                    </span>
                @endif
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Seats used</p>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                    {{ $seatCount }}<span class="text-base text-slate-400 font-bold"> / {{ $seatLimit === 0 ? '∞' : $seatLimit }}</span>
                </p>
            </div>
        </div>
    </div>

    <!-- Plans -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($plans as $key => $plan)
            @continue(!($plan['selectable'] ?? false))
            @php $isCurrent = $key === $currentKey; @endphp
            <div class="relative rounded-2xl border p-6 flex flex-col {{ ($plan['popular'] ?? false) ? 'border-brand-400 shadow-md' : 'border-slate-200/80' }} bg-white dark:bg-slate-800 dark:border-slate-700">
                @if($plan['popular'] ?? false)
                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-3 py-0.5 text-[11px] font-extrabold text-slate-900">Most popular</span>
                @endif
                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ $plan['name'] }}</h3>
                <p class="text-xs text-slate-400 mt-0.5 h-8">{{ $plan['blurb'] ?? '' }}</p>
                <div class="mt-3 flex items-baseline gap-1">
                    <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ $symbol }}{{ $plan['price'] }}</span>
                    <span class="text-sm text-slate-400 font-semibold">/mo</span>
                </div>
                <p class="text-xs font-bold text-slate-500 mt-2">{{ ($plan['seats'] ?? 0) === 0 ? 'Unlimited' : $plan['seats'] }} employees</p>

                <ul class="mt-4 space-y-1.5 flex-1">
                    @foreach($featureLabels as $fkey => $label)
                        @php $has = in_array('*', $plan['features'], true) || in_array($fkey, $plan['features'], true); @endphp
                        <li class="flex items-center gap-2 text-xs {{ $has ? 'text-slate-700 dark:text-slate-200' : 'text-slate-300 dark:text-slate-600 line-through' }}">
                            <i data-lucide="{{ $has ? 'check' : 'x' }}" class="h-3.5 w-3.5 {{ $has ? 'text-emerald-500' : 'text-slate-300' }}"></i> {{ $label }}
                        </li>
                    @endforeach
                </ul>

                <div class="mt-5">
                    @if($isCurrent && $tenant->status === 'active')
                        <button disabled class="w-full rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300 cursor-default">Current plan</button>
                    @else
                        <form action="{{ route('billing.subscribe') }}" method="POST">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $key }}">
                            <button type="submit" class="w-full rounded-xl {{ ($plan['popular'] ?? false) ? 'bg-brand-600 hover:bg-brand-700 text-slate-900' : 'bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-700 dark:hover:bg-slate-600' }} px-4 py-2.5 text-sm font-bold">Choose {{ $plan['name'] }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-center text-[11px] text-slate-400">Prices in {{ config('plans.currency', 'USD') }}. Card payment via Stripe is being connected — plan changes are recorded now and billed once live.</p>
</div>
@endsection
