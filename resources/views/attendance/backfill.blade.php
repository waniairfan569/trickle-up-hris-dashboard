@extends('layouts.hr-app')

@section('title', 'Backfill Attendance')
@section('breadcrumb', 'Attendance > Backfill')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="calendar-plus" class="h-6 w-6 text-brand-500"></i> Backfill Attendance
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Add attendance for all active employees on a day — everyone Present, no one late. Saved as manual records (safe from ZKTeco Rebuild / Clear).</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('attendance.backfill.store') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-5 dark:bg-slate-800 dark:border-slate-700"
          onsubmit="return confirm('Add attendance for all active employees on this date? Employees on approved leave are skipped.');">
        @csrf
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Date</label>
            <input type="date" name="date" value="{{ old('date', request('date')) }}" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Clock in</label>
                <input type="time" name="clock_in" value="{{ old('clock_in', '09:00') }}" required class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Clock out <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                <input type="time" name="clock_out" value="{{ old('clock_out') }}" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <p class="text-[11px] text-slate-400 mt-1">Leave empty for a clock-in only (e.g. today — they clock out on the device later).</p>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="overwrite" value="1" {{ old('overwrite') ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600">
            Overwrite employees who already have a record for this date
        </label>
        <p class="text-[11px] text-slate-400 -mt-2">Unchecked (recommended): only fill employees who have <b>no</b> record that day, so real clock-ins are kept.</p>

        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('attendance.live') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Back to Live Board</a>
            <button type="submit" class="btn-brand"><i data-lucide="check" class="h-4 w-4"></i> Add attendance</button>
        </div>
    </form>

    <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-xs text-slate-500 dark:bg-slate-900/40 dark:border-slate-700">
        <p class="font-bold text-slate-600 dark:text-slate-300 mb-1">Tips</p>
        <ul class="list-disc pl-5 space-y-1">
            <li><b>Yesterday (full day):</b> pick the date, clock-in 09:00, clock-out 18:00 → everyone Present, not late.</li>
            <li><b>Today (still working):</b> pick today, clock-in 09:00, leave clock-out empty. When they punch out on the device, it fills their clock-out.</li>
            <li>People on approved leave are automatically skipped (they stay On Leave).</li>
        </ul>
    </div>
</div>
@endsection
