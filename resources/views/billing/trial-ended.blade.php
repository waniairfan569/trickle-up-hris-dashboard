@extends('layouts.hr-app')

@section('title', 'Free trial ended')
@section('breadcrumb', 'Trial ended')

@section('content')
<div class="max-w-2xl mx-auto py-10 px-4">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:border-slate-700 dark:bg-slate-800">
        <div class="bg-gradient-to-br from-rose-500 to-orange-600 px-8 py-10 text-center text-white">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25">
                <i data-lucide="alarm-clock-off" class="h-7 w-7"></i>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">Your free trial has ended</h1>
            <p class="mt-2 text-sm text-white/85">
                Your workspace is paused. Nothing has been deleted — choosing a plan restores full access instantly.
            </p>
        </div>

        <div class="px-8 py-7">
            @if ($canManage)
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Pick a plan to reactivate <span class="font-semibold text-slate-800 dark:text-white">every module</span>
                    for your whole team. Your people, attendance, documents and settings are all exactly as you left them.
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <a href="{{ route('billing.index') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        <i data-lucide="sparkles" class="h-4 w-4"></i> Choose a plan
                    </a>
                    <a href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('trial-logout').submit();"
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/40">
                        <i data-lucide="log-out" class="h-4 w-4"></i> Sign out
                    </a>
                    <form id="trial-logout" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                </div>
            @else
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Your workspace’s free trial has ended. Please ask a workspace administrator to choose a plan —
                    once they do, your access is restored right away.
                </p>
                <div class="mt-6">
                    <a href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('trial-logout').submit();"
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/40">
                        <i data-lucide="log-out" class="h-4 w-4"></i> Sign out
                    </a>
                    <form id="trial-logout" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                </div>
            @endif
        </div>
    </div>
</div>
<script>window.lucide && lucide.createIcons();</script>
@endsection
