@php
    // Live timesheet pinned to the top of the sidebar (before the nav links), on every
    // page. Shares the .ts-worked-* classes and the tsAttendance()/tick script with the
    // top-bar copy, so a single guarded loop updates every instance.
    use App\Services\AttendanceService;
    use App\Services\ShiftService;

    $tsUser = auth()->user();
    $tsStatus = $tsUser ? app(AttendanceService::class)->getTodayStatus($tsUser) : null;
@endphp

@if($tsStatus)
    @php
        $tsSessions = $tsStatus['sessions'] ?? [];
        $tsIn = count($tsSessions) ? end($tsSessions)['in'] : $tsStatus['clock_in'];
        $tsExpected = app(ShiftService::class)->getExpectedTimesForUserOnDate($tsUser, today());
        $tsGoalSeconds = ($tsExpected && !empty($tsExpected['start']) && !empty($tsExpected['end']))
            ? max(1, (int) $tsExpected['start']->diffInSeconds($tsExpected['end']))
            : 28800;
        $tsGoalH = intdiv($tsGoalSeconds, 3600);
        $tsGoalM = intdiv($tsGoalSeconds % 3600, 60);
        $tsGoalLabel = $tsGoalM > 0 ? ($tsGoalH . 'h ' . $tsGoalM . 'm') : ($tsGoalH . 'h');
        $tsWorked = (int) ($tsStatus['worked_seconds'] ?? 0);
        $tsGoalPct = min(100, max(0, round($tsWorked / $tsGoalSeconds * 100)));
        $tsClockedIn = $tsStatus['clock_in'] && !$tsStatus['clock_out'] && !($tsStatus['is_on_break'] ?? false);
        $tsLate = ($tsStatus['status'] ?? null) === 'late';
    @endphp

    <div class="rounded-2xl border border-slate-800 bg-slate-800/60 p-4">
        <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-teal-400">
                <i data-lucide="timer" class="h-4 w-4"></i> Timesheet
            </span>
            @if($tsLate)<span class="rounded-full bg-amber-400/15 px-2 py-0.5 text-[10px] font-bold text-amber-400">LATE</span>@endif
        </div>

        <div class="mt-1.5 text-3xl font-black tabular-nums leading-none text-white ts-worked-timer">{{ intdiv($tsWorked,3600) }}h {{ intdiv($tsWorked%3600,60) }}m</div>

        @if($tsStatus['clock_in'])
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-700">
                <div class="ts-worked-bar h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $tsGoalPct }}%"></div>
            </div>
            <div class="mt-2 flex items-center justify-between text-[11px] font-semibold text-slate-400">
                <span>@if($tsClockedIn)<span class="text-emerald-400">●</span> since {{ $tsIn }}@elseif($tsStatus['clock_out'])Completed @else Paused @endif</span>
                <span><span class="ts-worked-pct">{{ $tsGoalPct }}</span>% of {{ $tsGoalLabel }}</span>
            </div>
        @else
            <p class="mt-2 text-[11px] font-semibold text-slate-400">Not clocked in yet</p>
        @endif

        <div class="mt-3.5">
            @if(!$tsStatus['clock_in'])
                <button type="button" onclick="tsAttendance('clock-in')" class="w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 transition">Clock In</button>
            @elseif($tsClockedIn)
                <button type="button" onclick="tsAttendance('clock-out')" class="flex w-full items-center justify-center gap-2 rounded-xl bg-white px-3 py-2.5 text-sm font-bold text-slate-900 hover:bg-slate-200 transition"><span class="h-2.5 w-2.5 bg-slate-900"></span> Clock Out</button>
            @else
                <button type="button" onclick="tsAttendance('clock-in')" class="w-full rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700 transition">Clock In Again</button>
            @endif
        </div>
    </div>

    @include('layouts.partials.timesheet-script', ['tsWorked' => $tsWorked, 'tsGoalSeconds' => $tsGoalSeconds, 'tsClockedIn' => $tsClockedIn])
@endif
