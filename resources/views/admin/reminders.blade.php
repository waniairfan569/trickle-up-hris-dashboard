@extends('layouts.hr-app')

@section('title', 'Reminders')
@section('breadcrumb', 'Reminders')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="bell-ring" class="h-6 w-6 text-brand-500"></i> Daily reminders
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Who's working from home tomorrow, and who was late today. Admins get these in the app at the time set below.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- WFH tomorrow --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><i data-lucide="house-wifi" class="h-4 w-4 text-indigo-500"></i> WFH tomorrow</h2>
                <span class="text-xs font-bold rounded-full bg-indigo-50 text-indigo-600 px-2.5 py-0.5 dark:bg-indigo-500/10 dark:text-indigo-400">{{ $wfhTomorrow->count() }}</span>
            </div>
            @forelse($wfhTomorrow as $u)
                <a href="{{ route('employees.profile', $u->id) }}" class="flex items-center gap-3 px-5 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50/70 dark:border-slate-700/40 dark:hover:bg-slate-700/30 transition">
                    <span class="h-8 w-8 shrink-0 rounded-full overflow-hidden bg-gradient-to-br from-brand-400 to-indigo-500 grid place-items-center text-white text-[11px] font-bold">
                        @if($u->avatar_url)<img src="{{ $u->avatar_url }}" class="h-full w-full object-cover">@else{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}@endif
                    </span>
                    <div class="min-w-0"><p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ trim($u->first_name.' '.$u->last_name) }}</p><p class="text-[11px] text-slate-400 truncate">{{ $u->email }}</p></div>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No one is approved for WFH tomorrow.</p>
            @endforelse
        </div>

        {{-- Late today --}}
        <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2"><i data-lucide="clock-alert" class="h-4 w-4 text-amber-500"></i> Late today</h2>
                <span class="text-xs font-bold rounded-full bg-amber-50 text-amber-600 px-2.5 py-0.5 dark:bg-amber-500/10 dark:text-amber-400">{{ $lateToday->count() }}</span>
            </div>
            @forelse($lateToday as $u)
                <a href="{{ route('employees.profile', $u->id) }}" class="flex items-center gap-3 px-5 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50/70 dark:border-slate-700/40 dark:hover:bg-slate-700/30 transition">
                    <span class="h-8 w-8 shrink-0 rounded-full overflow-hidden bg-gradient-to-br from-amber-400 to-orange-500 grid place-items-center text-white text-[11px] font-bold">
                        @if($u->avatar_url)<img src="{{ $u->avatar_url }}" class="h-full w-full object-cover">@else{{ strtoupper(substr($u->first_name,0,1).substr($u->last_name,0,1)) }}@endif
                    </span>
                    <div class="min-w-0"><p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ trim($u->first_name.' '.$u->last_name) }}</p><p class="text-[11px] text-slate-400 truncate">{{ $u->email }}</p></div>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No late arrivals today. 🎉</p>
            @endforelse
        </div>
    </div>

    {{-- Settings (super admin) --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="settings-2" class="h-4 w-4 text-brand-500"></i> Reminder schedule</h2>
        <p class="text-xs text-slate-400 mt-0.5">Turn each reminder on and choose when admins get it (times in {{ $settings->effectiveTimezone() }}).</p>

        @if($canManage)
            <form method="POST" action="{{ route('admin.reminders.settings') }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="wfh_enabled" value="1" @checked($settings->wfh_enabled) class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">WFH-tomorrow reminder</span>
                    </label>
                    <div class="flex items-center gap-2"><span class="text-xs font-semibold text-slate-400">at</span>
                        <input type="time" name="wfh_send_time" value="{{ $settings->timeLabel('wfh_send_time') }}" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input type="checkbox" name="late_enabled" value="1" @checked($settings->late_enabled) class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Late-today reminder</span>
                    </label>
                    <div class="flex items-center gap-2"><span class="text-xs font-semibold text-slate-400">at</span>
                        <input type="time" name="late_send_time" value="{{ $settings->timeLabel('late_send_time') }}" required class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 pt-1">
                    <button type="submit" class="btn-brand btn-sm">Save schedule</button>
                    <button type="submit" form="reminders-send-now" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/40">Send to admins now</button>
                </div>
            </form>
            <form id="reminders-send-now" method="POST" action="{{ route('admin.reminders.send-now') }}" class="hidden">@csrf</form>
        @else
            <div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <p><i data-lucide="{{ $settings->wfh_enabled ? 'check-circle' : 'circle' }}" class="inline h-4 w-4 {{ $settings->wfh_enabled ? 'text-emerald-500' : 'text-slate-300' }}"></i> WFH-tomorrow — {{ $settings->wfh_enabled ? 'on at '.$settings->timeLabel('wfh_send_time') : 'off' }}</p>
                <p><i data-lucide="{{ $settings->late_enabled ? 'check-circle' : 'circle' }}" class="inline h-4 w-4 {{ $settings->late_enabled ? 'text-emerald-500' : 'text-slate-300' }}"></i> Late-today — {{ $settings->late_enabled ? 'on at '.$settings->timeLabel('late_send_time') : 'off' }}</p>
                <p class="text-xs text-slate-400 pt-1">Only a super admin can change these.</p>
            </div>
        @endif
    </div>
</div>
<script>window.lucide && lucide.createIcons();</script>
@endsection
