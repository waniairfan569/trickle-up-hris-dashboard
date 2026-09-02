@extends('layouts.hr-app')

@section('title', 'Not on your plan')
@section('breadcrumb', $label)

@section('content')
<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:border-slate-700 dark:bg-slate-800">
        <div class="bg-gradient-to-br from-brand-500 to-indigo-600 px-8 py-10 text-center text-white">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25">
                <i data-lucide="lock" class="h-7 w-7"></i>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">{{ $label }} isn’t on your plan</h1>
            <p class="mt-2 text-sm text-white/80">
                Your workspace is on the <span class="font-semibold text-white">{{ $planName }}</span> plan, which doesn’t include this module.
            </p>
        </div>

        <div class="px-8 py-7">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                @if ($canManage)
                    Upgrade your plan to unlock <span class="font-semibold text-slate-800 dark:text-white">{{ $label }}</span>
                    and start using it right away — no data is lost, it simply becomes available.
                @else
                    <span class="font-semibold text-slate-800 dark:text-white">{{ $label }}</span> is available on a higher plan.
                    Ask a workspace admin to upgrade to enable it for your team.
                @endif
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($canManage)
                    <a href="{{ route('billing.index') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        <i data-lucide="sparkles" class="h-4 w-4"></i> View plans &amp; upgrade
                    </a>
                @endif
                <a href="{{ url()->previous() }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/40">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Go back
                </a>
            </div>
        </div>
    </div>
</div>
<script>window.lucide && lucide.createIcons();</script>
@endsection
