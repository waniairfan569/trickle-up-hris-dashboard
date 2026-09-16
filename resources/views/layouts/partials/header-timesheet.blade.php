@php
    // Top-bar live timesheet (before the notification bell). Borderless and inline:
    // the "Ongoing · % of goal" status sits next to the timer. Shares the .ts-worked-*
    // classes and the tsAttendance()/tick script with the sidebar copy.
    use App\Services\AttendanceService;
    use App\Services\ShiftService;

    $htUser = auth()->user();
    $htStatus = $htUser ? app(AttendanceService::class)->getTodayStatus($htUser) : null;
@endphp

@if($htStatus)
    @php
        $htSessions = $htStatus['sessions'] ?? [];
        $htIn = count($htSessions) ? end($htSessions)['in'] : $htStatus['clock_in'];
        $htExpected = app(ShiftService::class)->getExpectedTimesForUserOnDate($htUser, today());
        $htGoalSeconds = ($htExpected && !empty($htExpected['start']) && !empty($htExpected['end']))
            ? max(1, (int) $htExpected['start']->diffInSeconds($htExpected['end']))
            : 28800;
        $htGoalH = intdiv($htGoalSeconds, 3600);
        $htGoalM = intdiv($htGoalSeconds % 3600, 60);
        $htGoalLabel = $htGoalM > 0 ? ($htGoalH . 'h ' . $htGoalM . 'm') : ($htGoalH . 'h');
        $htWorked = (int) ($htStatus['worked_seconds'] ?? 0);
        $htGoalPct = min(100, max(0, round($htWorked / $htGoalSeconds * 100)));
        $htClockedIn = $htStatus['clock_in'] && !$htStatus['clock_out'] && !($htStatus['is_on_break'] ?? false);
        $htLate = ($htStatus['status'] ?? null) === 'late';
    @endphp

    <div class="hidden lg:flex items-center gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
            <i data-lucide="timer" class="h-4.5 w-4.5"></i>
        </span>

        <div class="min-w-[13rem] leading-tight">
            <div class="flex items-baseline gap-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-600 dark:text-teal-400">Timesheet</span>
                @if($htLate)<span class="text-[9px] font-bold text-amber-600 dark:text-amber-400">LATE</span>@endif
            </div>
            <div class="flex items-baseline gap-2 whitespace-nowrap">
                <span class="text-lg font-black tabular-nums leading-none text-slate-900 dark:text-white ts-worked-timer">{{ intdiv($htWorked,3600) }}h {{ intdiv($htWorked%3600,60) }}m</span>
                @if($htStatus['clock_in'])
                    <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500">
                        @if($htClockedIn)<span class="text-emerald-500">●</span> Ongoing @elseif($htStatus['clock_out'])Completed @else Paused @endif · <span class="ts-worked-pct">{{ $htGoalPct }}</span>% of {{ $htGoalLabel }}
                    </span>
                @else
                    <span class="text-[11px] font-semibold text-slate-400 dark:text-slate-500">Not clocked in</span>
                @endif
            </div>
            @if($htStatus['clock_in'])
                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                    <div class="ts-worked-bar h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $htGoalPct }}%"></div>
                </div>
            @endif
        </div>

        @if(!$htStatus['clock_in'])
            <button type="button" onclick="tsAttendance('clock-in')" class="shrink-0 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition">Clock In</button>
        @elseif($htClockedIn)
            <button type="button" onclick="tsAttendance('clock-out')" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 transition dark:bg-slate-700 dark:hover:bg-slate-600"><span class="h-2 w-2 bg-white"></span> Clock Out</button>
        @else
            <button type="button" onclick="tsAttendance('clock-in')" class="shrink-0 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-brand-700 transition">Clock In Again</button>
        @endif
    </div>

    @include('layouts.partials.timesheet-script', ['tsWorked' => $htWorked, 'tsGoalSeconds' => $htGoalSeconds, 'tsClockedIn' => $htClockedIn])
@endif
