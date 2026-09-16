@php
    // Live timesheet pinned to the top of the sidebar (before the nav links), so the
    // worked timer, goal progress and Clock In/Out are visible on every page. Dark-
    // styled for the sidebar. Included in both the mobile and desktop sidebars, so it
    // uses class-based selectors (not IDs) and a guarded script to update every copy
    // and avoid double-ticking.
    use App\Services\AttendanceService;
    use App\Services\ShiftService;

    $tsUser = auth()->user();
    $tsStatus = $tsUser ? app(AttendanceService::class)->getTodayStatus($tsUser) : null;
@endphp

@if($tsStatus)
    @php
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
        $tsBio = !$tsUser->usesDashboardClockIn();      // biometric = device only
        $tsLate = ($tsStatus['status'] ?? null) === 'late';
    @endphp

    <div class="mb-3 rounded-xl border border-slate-800 bg-slate-800/50 p-3">
        <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <i data-lucide="timer" class="h-3.5 w-3.5"></i> Timesheet
            </span>
            @if($tsLate)<span class="rounded-full bg-amber-400/15 px-1.5 py-0.5 text-[9px] font-bold text-amber-400">LATE</span>@endif
        </div>

        <div class="mt-1 text-xl font-black tabular-nums text-white sb-worked-timer">{{ intdiv($tsWorked,3600) }}h {{ intdiv($tsWorked%3600,60) }}m</div>

        @if($tsStatus['clock_in'])
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-700">
                <div class="sb-worked-bar h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $tsGoalPct }}%"></div>
            </div>
            <p class="mt-1.5 text-[10px] font-semibold text-slate-400">
                @if($tsClockedIn)Ongoing @elseif($tsStatus['clock_out'])Completed @else Paused @endif · <span class="sb-worked-pct">{{ $tsGoalPct }}</span>% of {{ $tsGoalLabel }}
            </p>
        @else
            <p class="mt-1.5 text-[10px] font-semibold text-slate-400">Not clocked in yet</p>
        @endif

        <div class="mt-3">
            @if($tsBio)
                <span class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-500/10 px-3 py-2 text-[11px] font-bold text-indigo-300" title="Attendance is recorded on the biometric device">
                    <i data-lucide="fingerprint" class="h-4 w-4"></i> Clock in on device
                </span>
            @elseif(!$tsStatus['clock_in'])
                <button type="button" onclick="tsAttendance('clock-in')" class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-[11px] font-bold text-white hover:bg-emerald-700 transition">Clock In</button>
            @elseif($tsClockedIn)
                <button type="button" onclick="tsAttendance('clock-out')" class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-[11px] font-bold text-slate-900 hover:bg-slate-200 transition"><span class="h-2 w-2 bg-slate-900"></span> Clock Out</button>
            @else
                <button type="button" onclick="tsAttendance('clock-in')" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-[11px] font-bold text-slate-900 hover:bg-brand-700 transition">Clock In Again</button>
            @endif
        </div>
    </div>

    <script>
    (function () {
        if (window.__sbTimesheet) return;   // included twice (mobile + desktop) — run once
        window.__sbTimesheet = true;

        var worked = {{ $tsWorked }};
        var goal = {{ $tsGoalSeconds }};
        var running = {{ $tsClockedIn ? 'true' : 'false' }};

        function fmt(s) { return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm'; }
        function tick() {
            if (!running) return;
            worked++;
            var p = Math.min(100, Math.max(0, Math.round(worked / goal * 100)));
            document.querySelectorAll('.sb-worked-timer').forEach(function (e) { e.textContent = fmt(worked); });
            document.querySelectorAll('.sb-worked-bar').forEach(function (e) { e.style.width = p + '%'; });
            document.querySelectorAll('.sb-worked-pct').forEach(function (e) { e.textContent = p; });
        }
        setInterval(tick, 1000);

        // Clock in/out from the sidebar; best-effort geolocation, server enforces geofencing.
        window.tsAttendance = window.tsAttendance || function (action) {
            var run = function (lat, lng) {
                var body = { _token: '{{ csrf_token() }}' };
                if (lat != null && lng != null) { body.lat = lat; body.lng = lng; }
                fetch('/attendance/' + action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(body)
                })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    if (res.d && res.d.success) { window.location.reload(); }
                    else { alert((res.d && res.d.message) || 'Could not update attendance.'); }
                })
                .catch(function () { alert('A network error occurred.'); });
            };
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function (p) { run(p.coords.latitude, p.coords.longitude); },
                    function () { run(null, null); },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            } else { run(null, null); }
        };
    })();
    </script>
@endif
