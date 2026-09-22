@extends('layouts.hr-app')

@section('title', 'Overtime Report')
@section('breadcrumb', 'Overtime Report')

@php
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $exportUrl = fn ($f) => route('overtime-report.export', array_merge(array_filter(request()->only(['preset', 'from', 'to'])), ['format' => $f]));
    $recipients = $settings->overtime_recipients ?? [];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="file-bar-chart-2" class="h-6 w-6 text-brand-500"></i> Overtime Report
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Approved overtime per employee for payroll — export for finance and set a recurring reminder.</p>
        </div>
        <a href="{{ route('company-forms.inbox', ['form' => optional($form)->id]) }}" class="inline-flex items-center gap-1.5 self-start rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Form Responses
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if(! $form)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300">
            <p class="font-bold mb-1">No Overtime Approval Form is set up yet.</p>
            <p>Create a form for overtime and mark it as the overtime form (its submissions become this report). Manage forms under <a href="{{ route('company-forms.index') }}" class="font-semibold underline">All forms</a>.</p>
        </div>
    @else

    {{-- Period picker + export --}}
    <div class="bg-white border border-slate-200/80 dark:border-slate-700 rounded-2xl shadow-sm dark:bg-slate-800 p-6" x-data="{ preset: '{{ $preset }}' }">
        <form method="GET" action="{{ route('overtime-report.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Period</label>
                <select name="preset" x-model="preset" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="today">Today</option>
                    <option value="week">This week</option>
                    <option value="month">This month</option>
                    <option value="last-month">Last month</option>
                    <option value="quarter">This quarter</option>
                    <option value="half">Last 6 months</option>
                    <option value="year">This year</option>
                    <option value="custom">Custom range…</option>
                </select>
            </div>
            <div x-show="preset === 'custom'" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" :required="preset === 'custom'" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div x-show="preset === 'custom'" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" :required="preset === 'custom'" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <button type="submit" class="btn-brand"><i data-lucide="search" class="h-4 w-4"></i> View</button>
            <div class="flex-1"></div>
            <a href="{{ $exportUrl('csv') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50"><i data-lucide="table" class="h-4 w-4"></i> CSV</a>
            <a href="{{ $exportUrl('pdf') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50"><i data-lucide="download" class="h-4 w-4"></i> PDF</a>
        </form>
        <p class="mt-3 text-xs text-slate-400">Showing <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $periodLabel }}</span> · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700"><div class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $employeeCount }}</div><div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Employees</div></div>
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700"><div class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $entryCount }}</div><div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Approved entries</div></div>
        <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700"><div class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $hasHours ? $fmt($totalHours) : '—' }}</div><div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total hours</div></div>
    </div>

    {{-- Results --}}
    <div class="bg-white border border-slate-200/80 dark:border-slate-700 rounded-2xl shadow-sm dark:bg-slate-800 overflow-hidden">
        @if($entryCount === 0)
            <div class="p-8 text-center text-sm text-slate-400">No approved overtime in this period.</div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/40">
                    <tr class="text-left text-[11px] uppercase tracking-wider text-slate-400">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3 text-right">Hours</th>
                        <th class="px-5 py-3">Details</th>
                        <th class="px-5 py-3">Approved</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($byEmployee as $group)
                    <tr class="bg-slate-50/70 dark:bg-slate-900/30 border-t border-slate-200 dark:border-slate-700">
                        <td colspan="4" class="px-5 py-2.5 font-bold text-slate-800 dark:text-slate-100">
                            {{ $group->employee }}
                            @if($group->email)<span class="ml-1 text-xs font-normal text-slate-400">· {{ $group->email }}</span>@endif
                            <span class="ml-2 text-xs font-semibold text-brand-600">{{ $group->entries }} {{ \Illuminate\Support\Str::plural('entry', $group->entries) }}@if($hasHours) · {{ $fmt($group->hours) }} h @endif</span>
                        </td>
                    </tr>
                    @foreach($group->rows as $r)
                        <tr class="border-t border-slate-100 dark:border-slate-700/50">
                            <td class="px-5 py-2.5 text-slate-700 dark:text-slate-200">{{ optional($r->date)->format('d M Y') }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold text-slate-700 dark:text-slate-200">{{ $r->hours !== null ? $fmt($r->hours) : '—' }}</td>
                            <td class="px-5 py-2.5 text-slate-500 dark:text-slate-400">{{ $r->details ?: '—' }}</td>
                            <td class="px-5 py-2.5 text-xs text-slate-400">{{ optional($r->approved_on)->format('d M Y') }}@if($r->approved_by) · {{ $r->approved_by }}@endif</td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-300 dark:border-slate-600 bg-brand-50/50 dark:bg-brand-500/5">
                        <td class="px-5 py-3 font-extrabold text-slate-900 dark:text-white">Grand total</td>
                        <td class="px-5 py-3 text-right font-extrabold text-slate-900 dark:text-white">{{ $hasHours ? $fmt($totalHours) : '—' }}</td>
                        <td colspan="2" class="px-5 py-3 text-xs text-slate-500">{{ $entryCount }} approved {{ \Illuminate\Support\Str::plural('entry', $entryCount) }} · {{ $employeeCount }} {{ \Illuminate\Support\Str::plural('employee', $employeeCount) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    {{-- Recurring reminder --}}
    <div class="bg-white border border-slate-200/80 dark:border-slate-700 rounded-2xl shadow-sm dark:bg-slate-800 overflow-hidden" x-data="{ freq: '{{ $settings->overtime_frequency ?? 'monthly' }}', on: {{ $settings->overtime_enabled ? 'true' : 'false' }} }">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10"><i data-lucide="bell-ring" class="h-5 w-5"></i></span>
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Recurring reminder</h2>
                <p class="text-xs text-slate-400">Get a dashboard notification + email to run this report on a schedule.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('overtime-report.reminder') }}" class="p-6 space-y-4">
            @csrf
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="overtime_enabled" value="1" x-model="on" class="rounded border-slate-300 text-brand-600"> Send this reminder automatically
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" :class="on ? '' : 'opacity-50 pointer-events-none'">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Frequency</label>
                    <select name="overtime_frequency" x-model="freq" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="monthly">Monthly</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div x-show="freq === 'monthly'">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Day of month</label>
                    <input type="number" name="overtime_day" min="1" max="31" value="{{ $settings->overtime_day ?: 1 }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div x-show="freq === 'weekly'" x-cloak>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Weekday</label>
                    <select name="overtime_weekday" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $n => $d)
                            <option value="{{ $n }}" @selected((int)($settings->overtime_weekday ?? 1) === $n)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Time</label>
                    <input type="time" name="overtime_send_time" value="{{ \Illuminate\Support\Carbon::parse($settings->overtime_send_time ?: '09:00')->format('H:i') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>

            <div :class="on ? '' : 'opacity-50 pointer-events-none'">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Recipients <span class="normal-case font-medium text-slate-400">(leave all unchecked to notify every admin)</span></label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto">
                    @foreach($adminUsers as $u)
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-600">
                            <input type="checkbox" name="overtime_recipients[]" value="{{ $u->id }}" @checked(in_array($u->id, $recipients)) class="rounded border-slate-300 text-brand-600">
                            <span class="min-w-0"><span class="font-semibold text-slate-700 dark:text-slate-200">{{ $u->full_name }}</span> <span class="text-xs text-slate-400">{{ $u->email }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-700/60">
                <p class="text-xs text-slate-400" x-show="on">Currently: <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $settings->overtimeScheduleLabel() }}</span></p>
                <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700 ml-auto">Save reminder</button>
            </div>
        </form>
    </div>

    {{-- History --}}
    @if($history->isNotEmpty())
    <div class="bg-white border border-slate-200/80 dark:border-slate-700 rounded-2xl shadow-sm dark:bg-slate-800 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Report history</h2>
            <p class="text-xs text-slate-400">Every export is logged so finance has a trail of what has been pulled.</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @foreach($history as $run)
                <div class="px-6 py-3 flex items-center gap-3 text-sm">
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ $run->format }}</span>
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $run->label ?: ($run->period_from->format('d M Y') . ' – ' . $run->period_to->format('d M Y')) }}</span>
                    <span class="text-xs text-slate-400">{{ $run->entry_count }} entries · {{ $fmt($run->total_hours) }} h</span>
                    <span class="ml-auto text-xs text-slate-400">{{ optional($run->generator)->full_name ?? '—' }} · {{ $run->created_at->format('d M Y, H:i') }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    @endif {{-- has form --}}
</div>
@endsection
