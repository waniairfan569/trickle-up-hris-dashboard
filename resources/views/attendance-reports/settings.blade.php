@extends('layouts.hr-app')

@section('title', 'Attendance Report Settings')
@section('breadcrumb', 'Attendance Reports')

@php
    $badge = [
        'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'failed' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'skipped' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ];
    $allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Attendance Report Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure the automatic daily attendance email report.</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 flex items-center gap-2"><i data-lucide="alert-circle" class="h-5 w-5"></i>{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form action="{{ route('attendance-reports.settings.update') }}" method="POST" class="space-y-6" x-data="{ enabled: {{ $settings->is_enabled ? 'true' : 'false' }} }">
        @csrf

        <!-- Master toggle -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Daily reports</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5" x-text="enabled ? 'Enabled — emails are sent automatically.' : 'Disabled — no emails will be sent.'"></p>
            </div>
            <input type="hidden" name="is_enabled" :value="enabled ? 1 : 0">
            <button type="button" @click="enabled = !enabled" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="enabled ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-600'">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="enabled ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
        </div>
        <div x-show="!enabled" x-cloak class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20">Reports are currently disabled. No emails will be sent.</div>

        <!-- Schedule -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Schedule</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Send time</label>
                    <input type="time" name="send_time" value="{{ \Carbon\Carbon::parse($settings->send_time)->format('H:i') }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <p class="text-[11px] text-slate-400 mt-1">Email sent at this time every working day.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Working days</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($allDays as $d)
                            <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold cursor-pointer dark:border-slate-600">
                                <input type="checkbox" name="working_days[]" value="{{ $d }}" @checked(in_array($d, $settings->working_days ?? [])) class="rounded border-slate-300 text-brand-600">
                                {{ $d }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Recipients</h2>
            @foreach([['send_to_super_admin','Super Admins',$counts['super_admin'].' super admin(s)'],['send_to_hr_admin','HR Admins',$counts['hr_admin'].' HR admin(s)'],['send_to_managers','Department Managers','Each manager receives only their team\'s data ('.$counts['manager'].')']] as [$field,$label,$hint])
                <label class="flex items-center justify-between cursor-pointer">
                    <span><span class="block text-sm font-semibold text-slate-800 dark:text-white">{{ $label }}</span><span class="block text-xs text-slate-400">{{ $hint }}</span></span>
                    <input type="checkbox" name="{{ $field }}" value="1" @checked($settings->$field) class="rounded border-slate-300 text-brand-600 h-5 w-5">
                </label>
            @endforeach
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Additional emails <span class="text-slate-400 normal-case font-medium">(one per line)</span></label>
                <textarea name="additional_emails_raw" rows="2" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ implode("\n", $settings->additional_emails ?? []) }}</textarea>
            </div>
        </div>

        <!-- Content -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Report content</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach([['include_late','Late employees section'],['include_absent','Absent employees section'],['include_early_departure','Early departure section'],['include_overtime','Overtime section'],['include_on_leave','On-leave section'],['include_full_table','Full attendance table'],['attach_excel','Attach Excel file']] as [$field,$label])
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 cursor-pointer">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked($settings->$field) class="rounded border-slate-300 text-brand-600"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="pt-2">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Grace period (minutes)</label>
                <input type="number" name="late_threshold_minutes" value="{{ $settings->late_threshold_minutes }}" min="0" max="60" class="w-32 rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <span class="text-[11px] text-slate-400 ml-2">Employees this many minutes or more late are highlighted.</span>
            </div>
            <div class="pt-2">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Subject line</label>
                <input type="text" name="report_subject_template" value="{{ $settings->report_subject_template }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <span class="text-[11px] text-slate-400">Use <code>@{{date}}</code> for the report date.</span>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700"><i data-lucide="save" class="h-4 w-4"></i> Save settings</button>
        </div>
    </form>

    <!-- Manual send + preview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Send report manually</h2>
            <p class="text-xs text-slate-400 mb-3">Immediately emails all configured recipients.</p>
            <form action="{{ route('attendance-reports.send-manual') }}" method="POST" class="flex items-end gap-2" onsubmit="return confirm('Send the report now to all configured recipients?');">
                @csrf
                <div class="flex-1"><label class="block text-[11px] font-bold text-slate-400 uppercase mb-1">Date</label><input type="date" name="date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-900 dark:bg-slate-700"><i data-lucide="send" class="h-4 w-4"></i> Send now</button>
            </form>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 flex flex-col justify-between">
            <div><h2 class="text-sm font-bold text-slate-800 dark:text-white mb-1">Preview</h2><p class="text-xs text-slate-400 mb-3">See today's report exactly as it will be emailed.</p></div>
            <a href="{{ route('attendance-reports.preview') }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="eye" class="h-4 w-4"></i> Preview today's report</a>
        </div>
    </div>

    <!-- History -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Send history</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                    <th class="px-5 py-3">Date</th><th class="px-5 py-3">Sent at</th><th class="px-5 py-3 text-right">Recipients</th><th class="px-5 py-3 text-right">Present</th><th class="px-5 py-3 text-right">Late</th><th class="px-5 py-3 text-right">Absent</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Trigger</th>
                </tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <td class="px-5 py-2.5 font-semibold text-slate-800 dark:text-white whitespace-nowrap">{{ $log->report_date->format('d M Y') }}</td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ optional($log->sent_at)->format('d M, H:i') ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-right">{{ count($log->sent_to ?? []) }}</td>
                            <td class="px-5 py-2.5 text-right text-emerald-600">{{ $log->present_count }}</td>
                            <td class="px-5 py-2.5 text-right text-amber-600">{{ $log->late_count }}</td>
                            <td class="px-5 py-2.5 text-right text-rose-600">{{ $log->absent_count }}</td>
                            <td class="px-5 py-2.5"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge[$log->status] ?? '' }}">{{ ucfirst($log->status) }}</span></td>
                            <td class="px-5 py-2.5 text-xs text-slate-400">{{ ucfirst($log->triggered_by) }}@if($log->triggeredBy) · {{ $log->triggeredBy->full_name }}@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center text-sm text-slate-400">No reports sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
