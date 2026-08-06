@extends('layouts.hr-app')

@section('title', 'My Attendance')
@section('breadcrumb', 'My Attendance')

@section('content')
@php
    $todayStr = \Carbon\Carbon::today()->toDateString();
    $navBase  = ['month' => $date->month, 'year' => $date->year];
    $tzSvc    = app(\App\Services\TimezoneService::class);

    // status → visual meta shared by the calendar cells, "today" card and legend.
    $statusMeta = function (?string $s) {
        return match ($s) {
            'present'           => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400', 'label' => 'Present'],
            'late'              => ['dot' => 'bg-amber-500',   'chip' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',       'label' => 'Late'],
            'absent'            => ['dot' => 'bg-rose-500',    'chip' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',            'label' => 'Absent'],
            'on_leave'          => ['dot' => 'bg-indigo-500',  'chip' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',    'label' => 'On leave'],
            'half_day'          => ['dot' => 'bg-sky-500',     'chip' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',                'label' => 'Half day'],
            'public_holiday'    => ['dot' => 'bg-violet-500',  'chip' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',    'label' => 'Holiday'],
            'early_departure'   => ['dot' => 'bg-orange-500',  'chip' => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',    'label' => 'Left early'],
            'missing_clock_out' => ['dot' => 'bg-orange-500',  'chip' => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',    'label' => 'No clock-out'],
            default             => ['dot' => 'bg-slate-300 dark:bg-slate-600', 'chip' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400', 'label' => '—'],
        };
    };

    // Month grid bounds — full weeks (Mon-start) covering the visible month.
    $gridStart = $date->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
    $gridEnd   = $date->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::MONDAY);
    $gridDays  = collect();
    for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) { $gridDays->push($d->copy()); }

    $wt = $weekTotalMinutes;
    $weekTotalLabel = intdiv($wt, 60) . 'h ' . ($wt % 60) . 'm';
@endphp

<div class="space-y-6" x-data="{ view: 'month' }" x-init="$nextTick(() => window.lucide && lucide.createIcons())">

    <!-- Month navigation -->
    <div class="flex items-center justify-between bg-white dark:bg-slate-800 p-3 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
        <a href="{{ route('attendance.my-history', ['month' => $date->copy()->subMonth()->month, 'year' => $date->copy()->subMonth()->year]) }}"
           class="h-9 w-9 grid place-items-center rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Previous month">
            <i data-lucide="chevron-left" class="w-5 h-5"></i>
        </a>
        <div class="flex items-center gap-2">
            <i data-lucide="calendar-days" class="w-5 h-5 text-brand-500"></i>
            <h2 class="text-lg sm:text-xl font-bold text-slate-800 dark:text-white">{{ $date->format('F Y') }}</h2>
        </div>
        <a href="{{ route('attendance.my-history', ['month' => $date->copy()->addMonth()->month, 'year' => $date->copy()->addMonth()->year]) }}"
           class="h-9 w-9 grid place-items-center rounded-xl text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Next month">
            <i data-lucide="chevron-right" class="w-5 h-5"></i>
        </a>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        @php
            $cards = [
                ['label' => 'Days Present', 'value' => $summary['present'], 'icon' => 'check-circle', 'tone' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-500/10'],
                ['label' => 'Days Late',    'value' => $summary['late'],    'icon' => 'alarm-clock',  'tone' => 'text-amber-600 dark:text-amber-400',   'bg' => 'bg-amber-50 dark:bg-amber-500/10'],
                ['label' => 'Days Absent',  'value' => $summary['absent'],  'icon' => 'user-x',       'tone' => 'text-rose-600 dark:text-rose-400',     'bg' => 'bg-rose-50 dark:bg-rose-500/10'],
                ['label' => 'Total Hours',  'value' => $summary['total_hours'] . 'h', 'icon' => 'timer', 'tone' => 'text-brand-600 dark:text-brand-400', 'bg' => 'bg-brand-50 dark:bg-brand-500/10'],
            ];
        @endphp
        @foreach($cards as $c)
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 sm:p-5 flex items-center gap-3.5">
                <span class="h-11 w-11 shrink-0 grid place-items-center rounded-xl {{ $c['bg'] }} {{ $c['tone'] }}">
                    <i data-lucide="{{ $c['icon'] }}" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <div class="text-2xl font-black text-slate-800 dark:text-white leading-none">{{ $c['value'] }}</div>
                    <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mt-1 truncate">{{ $c['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- View switcher + panels -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <h3 class="font-bold text-slate-800 dark:text-white">Attendance overview</h3>
            <div class="inline-flex rounded-xl border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-slate-900/40 self-start">
                @foreach(['today' => 'Today', 'week' => 'This week', 'month' => 'Month'] as $key => $label)
                    <button type="button" @click="view = '{{ $key }}'"
                            :class="view === '{{ $key }}' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                            class="px-3.5 py-1.5 text-xs font-bold rounded-lg transition">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- ── TODAY ─────────────────────────────────────────────── --}}
        <div x-show="view === 'today'" x-cloak>
            @php $trMeta = $statusMeta($todayRecord->status ?? null); @endphp
            @if($todayRecord)
                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ \Carbon\Carbon::today()->format('l') }}</p>
                            <p class="text-lg font-bold text-slate-800 dark:text-white">{{ \Carbon\Carbon::today()->format('d M Y') }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $trMeta['chip'] }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $trMeta['dot'] }}"></span>{{ $trMeta['label'] }}
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-3 mt-5">
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 p-4 text-center">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Clock in</p>
                            <p class="mt-1 text-lg font-black text-slate-800 dark:text-white font-mono">{{ $todayRecord->clock_in_local ?? '--:--' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 p-4 text-center">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Clock out</p>
                            <p class="mt-1 text-lg font-black text-slate-800 dark:text-white font-mono">{{ $todayRecord->clock_out_local ?? '--:--' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/40 p-4 text-center">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Hours</p>
                            <p class="mt-1 text-lg font-black text-brand-600 dark:text-brand-400">{{ $todayRecord->hours_worked ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 py-12 text-center">
                    <i data-lucide="coffee" class="w-10 h-10 mx-auto text-slate-300 mb-3"></i>
                    <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No attendance recorded today yet</p>
                    <p class="text-xs text-slate-400 mt-1">Your clock-in will appear here once you start your day.</p>
                </div>
            @endif
        </div>

        {{-- ── THIS WEEK ─────────────────────────────────────────── --}}
        <div x-show="view === 'week'" x-cloak>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $weekStart->format('d M') }} – {{ $weekStart->copy()->addDays(6)->format('d M Y') }}</span>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-bold text-brand-600 dark:text-brand-400">Week total: {{ $weekTotalLabel }}</span>
                    <div class="flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 px-1 py-1">
                        <a href="{{ route('attendance.my-history', array_merge($navBase, ['week' => $weekStart->copy()->subWeek()->toDateString()])) }}" class="h-7 w-7 grid place-items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
                        <a href="{{ route('attendance.my-history', $navBase) }}" class="px-2 text-[10px] font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white">This week</a>
                        <a href="{{ route('attendance.my-history', array_merge($navBase, ['week' => $weekStart->copy()->addWeek()->toDateString()])) }}" class="h-7 w-7 grid place-items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
                @foreach($weekDays as $day)
                    @php
                        $rec = $weekRecords[$day->toDateString()] ?? null;
                        $mins = $rec ? (int) $rec->total_minutes_worked : 0;
                        $isToday = $day->toDateString() === $todayStr;
                        $meta = $statusMeta($rec->status ?? null);
                    @endphp
                    <div class="rounded-xl border p-3 text-center {{ $isToday ? 'border-brand-300 bg-brand-50/40 dark:border-brand-500/30 dark:bg-brand-500/5' : 'border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30' }}">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $day->format('D') }}</div>
                        <div class="text-xs font-bold text-slate-600 dark:text-slate-300 mb-2">{{ $day->format('j M') }}</div>
                        @if($mins > 0)
                            <div class="rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400 text-xs font-bold py-2">{{ intdiv($mins, 60) }}h {{ $mins % 60 }}m</div>
                        @elseif($rec && $rec->status)
                            <div class="rounded-lg text-[10px] font-bold py-2 {{ $meta['chip'] }}">{{ $meta['label'] }}</div>
                        @else
                            <div class="rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-400 text-[10px] font-bold py-2">—</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── MONTH (calendar grid) ─────────────────────────────── --}}
        <div x-show="view === 'month'">
            <div class="grid grid-cols-7 gap-1.5 sm:gap-2 mb-1.5">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd)
                    <div class="text-center text-[10px] font-bold uppercase tracking-wider text-slate-400 py-1">{{ $wd }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                @foreach($gridDays as $day)
                    @php
                        $inMonth = $day->month === $date->month && $day->year === $date->year;
                        $ds = $day->toDateString();
                        $rec = $monthRecords[$ds] ?? null;
                        $isToday = $ds === $todayStr;
                        $isWeekend = $day->isWeekend();
                        $meta = $statusMeta($rec->status ?? null);
                        $mins = $rec ? (int) $rec->total_minutes_worked : 0;
                        $correctable = $rec && in_array($rec->status, ['absent', 'missing_clock_out', 'late', 'early_departure']);
                        $ci = $rec && $rec->clock_in ? $tzSvc->formatForUser($rec->clock_in, auth()->user(), 'H:i') : '';
                        $co = $rec && $rec->clock_out ? $tzSvc->formatForUser($rec->clock_out, auth()->user(), 'H:i') : '';
                        $cellBase = 'group relative min-h-[62px] sm:min-h-[84px] rounded-xl border p-2 flex flex-col text-left transition';
                        if (!$inMonth) {
                            $cellCls = 'border-transparent opacity-40';
                        } elseif ($isToday) {
                            $cellCls = 'border-brand-300 dark:border-brand-500/40 ring-2 ring-brand-400/60 bg-white dark:bg-slate-800';
                        } elseif ($isWeekend) {
                            $cellCls = 'border-slate-100 dark:border-slate-700/60 bg-slate-50/60 dark:bg-slate-900/30';
                        } else {
                            $cellCls = 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800';
                        }
                        if ($correctable) { $cellCls .= ' cursor-pointer hover:border-brand-300 hover:shadow-sm'; }
                    @endphp
                    <div class="{{ $cellBase }} {{ $cellCls }}"
                         @if($correctable) role="button" tabindex="0" title="Submit a correction for this day"
                            onclick="openCorrectionModal('{{ $ds }}', '{{ $ci }}', '{{ $co }}')" @endif>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold {{ $isToday ? 'text-brand-600 dark:text-brand-400' : 'text-slate-600 dark:text-slate-300' }}">{{ $day->format('j') }}</span>
                            @if($rec)<span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>@endif
                        </div>

                        @if($rec)
                            <div class="mt-auto space-y-0.5">
                                @if($mins > 0)
                                    <div class="text-[11px] font-black text-slate-700 dark:text-slate-200">{{ intdiv($mins, 60) }}h {{ $mins % 60 }}m</div>
                                @endif
                                <div class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-bold {{ $meta['chip'] }}">{{ $meta['label'] }}</div>
                            </div>
                            @if($correctable)
                                <i data-lucide="wrench" class="absolute right-1.5 bottom-1.5 h-3 w-3 text-brand-500 opacity-0 group-hover:opacity-100 transition"></i>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-5 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                @foreach(['present','late','absent','on_leave','half_day','public_holiday'] as $s)
                    @php $m = $statusMeta($s); @endphp
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        <span class="h-2 w-2 rounded-full {{ $m['dot'] }}"></span>{{ $m['label'] }}
                    </span>
                @endforeach
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-400 ml-auto">
                    <i data-lucide="wrench" class="h-3 w-3"></i> Click a flagged day to submit a correction
                </span>
            </div>
        </div>
    </div>

    <!-- Details table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-white">Attendance details</h3>
            <span class="text-xs font-semibold text-slate-400">{{ $date->format('F Y') }}</span>
        </div>

        @if(session('success'))
            <div class="p-4 bg-emerald-50 text-emerald-700 border-b border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 font-medium text-sm flex items-center">
                <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>{{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 font-medium uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Clock In</th>
                        <th class="px-6 py-3">Clock Out</th>
                        <th class="px-6 py-3">Hours Worked</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($records as $record)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-slate-800 dark:text-white">{{ $record->date->format('M d, Y') }}</span>
                                <div class="text-xs text-slate-400">{{ $record->date->format('l') }}</div>
                            </td>
                            @php $sessions = $record->sessionSequence(); @endphp
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">
                                {{ $record->clock_in_local ?? '--:--' }}
                                @if(count($sessions) > 1)
                                    <div class="text-[10px] text-slate-400 font-sans mt-0.5">{{ collect($sessions)->map(fn ($s) => $s['in'] . '–' . ($s['out'] ?? 'now'))->implode(' · ') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">
                                {{ $record->clock_out_local ?? '--:--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800 dark:text-white">
                                {{ $record->hours_worked ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $record->status_color }}">
                                    {{ str_replace('_', ' ', Str::title($record->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if(in_array($record->status, ['absent', 'missing_clock_out', 'late', 'early_departure']))
                                    <button onclick="openCorrectionModal('{{ $record->date->format('Y-m-d') }}', '{{ $record->clock_in ? $tzSvc->formatForUser($record->clock_in, auth()->user(), 'H:i') : '' }}', '{{ $record->clock_out ? $tzSvc->formatForUser($record->clock_out, auth()->user(), 'H:i') : '' }}')" class="text-brand-600 hover:text-brand-800 dark:text-brand-400 font-medium text-sm transition">
                                        Submit Correction
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <i data-lucide="calendar-x" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
                                <p>No attendance records found for this month.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $records->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>

<!-- Correction Modal -->
<div id="correction-modal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white">Submit Attendance Correction</h3>
            <button onclick="closeCorrectionModal()" class="text-slate-400 hover:text-slate-600 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="{{ route('attendance.correction.submit') }}" method="POST">
            @csrf
            <div class="p-6 space-y-4">
                <input type="hidden" name="correction_date" id="correction_date">

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Date</label>
                    <div id="correction_date_display" class="font-medium text-slate-800 dark:text-white bg-slate-50 dark:bg-slate-900/50 p-2 rounded-lg border border-slate-200 dark:border-slate-700"></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Requested Clock In</label>
                        <input type="time" name="requested_clock_in" id="requested_clock_in" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Requested Clock Out</label>
                        <input type="time" name="requested_clock_out" id="requested_clock_out" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason for Correction <span class="text-red-500">*</span></label>
                    <textarea name="reason" required rows="3" placeholder="Forgot to clock out, system error, etc." class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
                <button type="button" onclick="closeCorrectionModal()" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-brand">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCorrectionModal(date, clockIn, clockOut) {
        document.getElementById('correction_date').value = date;
        document.getElementById('correction_date_display').innerText = date;
        document.getElementById('requested_clock_in').value = clockIn;
        document.getElementById('requested_clock_out').value = clockOut;
        document.getElementById('correction-modal').classList.remove('hidden');
    }

    function closeCorrectionModal() {
        document.getElementById('correction-modal').classList.add('hidden');
    }
</script>
@endsection
