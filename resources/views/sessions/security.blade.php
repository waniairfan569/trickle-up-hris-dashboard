@extends('layouts.hr-app')

@section('title', 'Security')
@section('breadcrumb', 'Account > Security')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="shield-check" class="h-6 w-6 text-brand-500"></i> Security
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">You stay signed in for 360 days, even after closing the browser.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <!-- Change password -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-start gap-3 mb-4">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="key-round" class="h-5 w-5"></i></span>
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Change password</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Use at least 8 characters with letters, numbers and a symbol.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('password.update') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Current password</label>
                <input type="password" name="current_password" required autocomplete="current-password" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">New password</label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Confirm new</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Update password</button>
            </div>
        </form>
    </div>

    <!-- Active sessions -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Where you're signed in ({{ $sessions->count() }})</h2>
        </div>
        @forelse($sessions as $s)
            <div class="flex items-center gap-3 px-5 py-3 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700"><i data-lucide="monitor" class="h-4 w-4"></i></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-white">{{ $s['device'] }}
                        @if($s['is_current'])<span class="ml-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">This device</span>@endif
                    </p>
                    <p class="text-xs text-slate-400">{{ $s['ip'] ?: 'Unknown IP' }} · last active {{ $s['last_active']->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-400">No active sessions.</p>
        @endforelse
    </div>

    <!-- Sign out other devices -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700"
         x-data="{ open: false }">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Sign out other devices</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-md">Signs you out everywhere except this device. Use this if you signed in on a shared or lost device.</p>
            </div>
            <button type="button" @click="open = !open" class="shrink-0 rounded-xl bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10">Sign out others</button>
        </div>
        <form x-show="open" x-cloak method="POST" action="{{ route('account.logout-others') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-700/60">
            @csrf
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Confirm your password</label>
                <input type="password" name="password" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <button type="submit" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700">Confirm sign out</button>
        </form>
    </div>
</div>
@endsection
