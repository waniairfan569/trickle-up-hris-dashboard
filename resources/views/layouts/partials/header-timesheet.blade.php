@php
    // Compact live timesheet for the top bar — visible on every page. Mirrors the
    // dashboard timesheet card (live worked timer, goal progress, clock in/out) in
    // a condensed form. Self-contained: unique IDs + its own script so it never
    // clashes with the dashboard clock widget.
    use App\Services\AttendanceService;
    use App\Services\ShiftService;

    $htsUser = auth()->user();
    $htsStatus = $htsUser ? app(AttendanceService::class)->getTodayStatus($htsUser) : null;
@endphp

@if($htsStatus)
    @php
        $htsSessions = $htsStatus['sessions'] ?? [];
        $htsIn = count($htsSessions) ? end($htsSessions)['in'] : $htsStatus['clock_in'];
        $htsExpected = app(ShiftService::class)->getExpectedTimesForUserOnDate($htsUser, today());
        $htsGoalSeconds = ($htsExpected && !empty($htsExpected['start']) && !empty($htsExpected['end']))
            ? max(1, (int) $htsExpected['start']->diffInSeconds($htsExpected['end']))
            : 28800;
        $htsGoalH = intdiv($htsGoalSeconds, 3600);
        $htsGoalM = intdiv($htsGoalSeconds % 3600, 60);
        $htsGoalLabel = $htsGoalM > 0 ? ($htsGoalH . 'h ' . $htsGoalM . 'm') : ($htsGoalH . 'h');
        $htsWorked = (int) ($htsStatus['worked_seconds'] ?? 0);
        $htsGoalPct = min(100, max(0, round($htsWorked / $htsGoalSeconds * 100)));
        $htsClockedIn = $htsStatus['clock_in'] && !$htsStatus['clock_out'] && !($htsStatus['is_on_break'] ?? false);
        $htsBio = !$htsUser->usesDashboardClockIn();               // biometric = device only
        $htsLate = ($htsStatus['status'] ?? null) === 'late';
    @endphp

    <div id="hdr-timesheet" class="hidden md:flex items-center gap-3 rounded-xl border border-slate-200/80 bg-slate-50/70 pl-3 pr-1.5 py-1 dark:border-slate-700 dark:bg-slate-900/40">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-slate-500 shadow-sm dark:bg-slate-800 dark:text-slate-300">
            <i data-lucide="timer" class="h-4 w-4"></i>
        </span>

        <div class="min-w-[8.5rem] leading-tight">
            <div class="flex items-baseline gap-1.5">
                <span class="text-sm font-black tabular-nums text-slate-900 dark:text-white" id="hdr-worked-timer">{{ intdiv($htsWorked,3600) }}h {{ intdiv($htsWorked%3600,60) }}m</span>
                @if($htsLate)<span class="text-[9px] font-bold text-amber-600 dark:text-amber-400">LATE</span>@endif
            </div>
            @if($htsStatus['clock_in'])
                <div class="mt-1 h-1 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                    <div id="hdr-worked-bar" class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $htsGoalPct }}%"></div>
                </div>
                <p class="mt-0.5 text-[9px] font-semibold text-slate-400 dark:text-slate-500">
                    @if($htsClockedIn)Ongoing @elseif($htsStatus['clock_out'])Done @else Paused @endif · <span id="hdr-worked-pct">{{ $htsGoalPct }}</span>% of {{ $htsGoalLabel }}
                </p>
            @else
                <p class="mt-0.5 text-[9px] font-semibold text-slate-400 dark:text-slate-500">Not clocked in</p>
            @endif
        </div>

        {{-- Action --}}
        @if($htsBio)
            <span class="hidden lg:inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2 py-1 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300" title="Attendance is recorded on the biometric device">
                <i data-lucide="fingerprint" class="h-3.5 w-3.5"></i> On device
            </span>
        @elseif(!$htsStatus['clock_in'])
            <button type="button" onclick="hdrAttendance('clock-in')" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-emerald-700 transition">Clock In</button>
        @elseif($htsClockedIn)
            <button type="button" onclick="hdrAttendance('clock-out')" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-3 py-1.5 text-[11px] font-bold text-white hover:bg-slate-900 transition dark:bg-slate-700 dark:hover:bg-slate-600"><span class="h-2 w-2 bg-white"></span> Clock Out</button>
        @else
            <button type="button" onclick="hdrAttendance('clock-in')" class="rounded-lg bg-brand-600 px-3 py-1.5 text-[11px] font-bold text-slate-900 hover:bg-brand-700 transition">Clock In</button>
        @endif
    </div>

    <script>
    (function () {
        // Skip if a page already wired the timer (avoid double-ticking with the dashboard widget's own clock).
        var el = document.getElementById('hdr-worked-timer');
        if (!el) return;

        var worked = {{ $htsWorked }};
        var goal = {{ $htsGoalSeconds }};
        var running = {{ $htsClockedIn ? 'true' : 'false' }};
        var bar = document.getElementById('hdr-worked-bar');
        var pct = document.getElementById('hdr-worked-pct');

        function fmt(s) { return Math.floor(s/3600) + 'h ' + Math.floor((s%3600)/60) + 'm'; }
        function tick() {
            if (!running) return;
            worked++;
            el.textContent = fmt(worked);
            var p = Math.min(100, Math.max(0, Math.round(worked / goal * 100)));
            if (bar) bar.style.width = p + '%';
            if (pct) pct.textContent = p;
        }
        // Update once a minute is enough for the header; keeps it cheap.
        setInterval(tick, 1000);

        // Clock in/out from the header. Sends best-effort geolocation (server enforces
        // geofence and returns a message if blocked); never blocks on location.
        window.hdrAttendance = window.hdrAttendance || function (action) {
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
