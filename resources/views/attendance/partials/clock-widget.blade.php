@php
    $status = app(\App\Services\AttendanceService::class)->getTodayStatus(auth()->user());
    $activeAssignment = auth()->user()->shiftAssignments()->with('shift')->where('assignment_type', 'recurring')->first();
    $expectedStart = ($activeAssignment && $activeAssignment->shift) ? \Carbon\Carbon::parse($activeAssignment->shift->start_time)->format('h:i A') : '--:--';
    $sessions = $status['sessions'] ?? [];
    $currentIn = count($sessions) ? end($sessions)['in'] : $status['clock_in'];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700" id="clock-widget">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <!-- Time + date -->
        <div class="flex items-baseline gap-2 min-w-0">
            <span class="text-2xl font-black text-brand-600 tracking-tight" id="live-time"></span>
            <span class="text-sm font-semibold text-slate-500 dark:text-slate-300 truncate" id="live-date"></span>
            @php
                $tzRaw = $userTimezone ?? config('app.timezone');
                $tzLabel = ['Asia/Karachi' => 'Islamabad'][$tzRaw]
                    ?? \Illuminate\Support\Str::of($tzRaw)->afterLast('/')->replace('_', ' ');
            @endphp
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider hidden md:inline">{{ $tzLabel }}<span id="live-tz-abbr">{{ isset($userTimezoneAbbr) ? ' · '.$userTimezoneAbbr : '' }}</span></span>
        </div>

        <!-- Status + action -->
        @php
            $isLate = ($status['status'] ?? null) === 'late';
            $bioMode = !auth()->user()->usesDashboardClockIn(); // biometric = device only
        @endphp
        <div class="flex items-center gap-3 sm:flex-shrink-0">
            @if(!$status['clock_in'])
                @if($bioMode)
                    <span class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-4 py-2.5 text-xs font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"><i data-lucide="fingerprint" class="h-4 w-4"></i> Clock in on the biometric device</span>
                @else
                    <span class="text-xs text-slate-400 hidden sm:inline">Expected {{ $expectedStart }}</span>
                    <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="bg-green-600 hover:bg-green-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-sm transition">
                        Clock In
                    </button>
                @endif
            @elseif($status['clock_in'] && !$status['clock_out'])
                @if($isLate)<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700"><i data-lucide="alarm-clock" class="h-3 w-3"></i> Late{{ $status['late_minutes'] ? ' · '.$status['late_minutes'].'m' : '' }}</span>@endif
                <span class="text-xs text-slate-500 dark:text-slate-400">In at {{ $currentIn }} · <span class="font-bold text-slate-800 dark:text-white" id="live-worked-timer">0h 0m 0s</span></span>
                @if($bioMode)
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-300"><i data-lucide="fingerprint" class="h-3.5 w-3.5"></i> Clock out on device</span>
                @else
                    <button id="btn-clock-out" onclick="attendanceAction('clock-out')" class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm px-6 py-2.5 rounded-lg shadow-sm transition flex items-center justify-center">
                        <span class="w-2.5 h-2.5 bg-white mr-2"></span> Clock out
                    </button>
                @endif
            @elseif($status['clock_out'])
                @if($isLate)<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700"><i data-lucide="alarm-clock" class="h-3 w-3"></i> Late{{ $status['late_minutes'] ? ' · '.$status['late_minutes'].'m' : '' }}</span>@endif
                <span class="text-xs text-slate-500 dark:text-slate-400">Completed · <span class="font-bold text-slate-800 dark:text-white" id="completed-worked-timer">0h 0m 0s</span></span>
                @unless($bioMode)
                    <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="bg-brand-600 hover:bg-brand-700 text-slate-900 font-bold text-sm px-6 py-2.5 rounded-lg shadow-sm transition">
                        Clock In Again
                    </button>
                @endunless
            @endif
        </div>
    </div>

    <!-- Geofence Status Badge -->
    <div id="geofence-status" class="hidden text-xs font-medium py-1.5 px-3 rounded-md mt-2"></div>
</div>

<script>
    let workedSeconds = Math.floor({{ $status['worked_seconds'] ?? 0 }});
    const isClockedIn = {{ ($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break']) ? 'true' : 'false' }};
    
    function formatWorkedTime(totalSeconds) {
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = Math.floor(totalSeconds % 60);
        return `${h}h ${m}m ${s}s`;
    }

    // Initialize display immediately
    const liveTimerEl = document.getElementById('live-worked-timer');
    const completedTimerEl = document.getElementById('completed-worked-timer');
    if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
    if (completedTimerEl) completedTimerEl.innerText = formatWorkedTime(workedSeconds);

    // Render the live clock in the employee's EFFECTIVE timezone, not the
    // browser's local timezone.
    const userTz = '{{ app(\App\Services\TimezoneService::class)->getEffectiveTimezone(auth()->user()) }}';
    const liveTimeFmt = new Intl.DateTimeFormat('en-GB', { timeZone: userTz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
    const liveDateFmt = new Intl.DateTimeFormat('en-US', { timeZone: userTz, weekday: 'long', month: 'long', day: 'numeric' });

    function updateClock() {
        const now = new Date();
        try {
            document.getElementById('live-time').innerText = liveTimeFmt.format(now);
            document.getElementById('live-date').innerText = liveDateFmt.format(now);
        } catch (e) {
            document.getElementById('live-time').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
            document.getElementById('live-date').innerText = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }

        if (isClockedIn) {
            workedSeconds++;
            if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    let geofenceData = null;
    let currentLat = null;
    let currentLng = null;
    let isGeofenceChecking = false;

    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = (lat2-lat1)*Math.PI/180;
        const dLng = (lng2-lng1)*Math.PI/180;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
        return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
    }

    function updateGeofenceUI(allowed, message, type = 'danger') {
        const statusEl = document.getElementById('geofence-status');
        const clockInBtn = document.getElementById('btn-clock-in');
        const clockOutBtn = document.getElementById('btn-clock-out');

        statusEl.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700', 'bg-slate-100', 'text-slate-700', 'bg-amber-100', 'text-amber-700');
        
        if (type === 'danger') {
            statusEl.classList.add('bg-red-100', 'text-red-700');
        } else if (type === 'success') {
            statusEl.classList.add('bg-green-100', 'text-green-700');
        } else if (type === 'warning') {
            statusEl.classList.add('bg-amber-100', 'text-amber-700');
        } else {
            statusEl.classList.add('bg-slate-100', 'text-slate-700');
        }
        
        statusEl.innerHTML = message;

        if (clockInBtn) clockInBtn.disabled = !allowed;
        if (clockOutBtn) clockOutBtn.disabled = !allowed;
    }

    function checkLocation() {
        if (!geofenceData || !geofenceData.geofence_enabled) return;

        if (!navigator.geolocation) {
            updateGeofenceUI(false, "Geolocation is not supported by your browser.", "danger");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                currentLat = position.coords.latitude;
                currentLng = position.coords.longitude;
                
                let isAllowed = false;
                let nearestDistance = null;
                let nearestOffice = null;

                for (let office of geofenceData.office_locations) {
                    if (office.allow_remote) {
                        isAllowed = true;
                        break;
                    }
                    
                    const dist = haversine(currentLat, currentLng, office.lat, office.lng);
                    
                    if (dist <= office.radius) {
                        isAllowed = true;
                        updateGeofenceUI(true, `✓ ${office.name} (${dist}m away)`, "success");
                        return;
                    }

                    if (nearestDistance === null || dist < nearestDistance) {
                        nearestDistance = dist;
                        nearestOffice = office;
                    }
                }

                if (!isAllowed) {
                    if (nearestOffice) {
                        updateGeofenceUI(false, `✗ Outside office (${nearestDistance}m away from ${nearestOffice.name} — need to be within ${nearestOffice.radius}m)`, "danger");
                    } else {
                        updateGeofenceUI(false, "✗ No active office assigned.", "danger");
                    }
                }
                clearTimeout(locationTimeout);
            },
            (error) => {
                clearTimeout(locationTimeout);
                let msg = "Location access is required to clock in. Please allow location in your browser settings.";
                updateGeofenceUI(false, "✗ " + msg, "danger");
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
        );

        // Fallback timeout in case the browser prompt is ignored and never resolves
        let locationTimeout = setTimeout(() => {
            if (currentLat === null) {
                updateGeofenceUI(false, "✗ Location request timed out. Please check browser permissions.", "warning");
            }
        }, 10000);
    }

    // Init Geofence
    updateGeofenceUI(false, "Checking your location... <span class='animate-pulse'>⏳</span>", "info");
    
    fetch('/attendance/office-status')
        .then(res => res.json())
        .then(data => {
            geofenceData = data;
            if (!data.geofence_enabled) {
                document.getElementById('geofence-status').classList.add('hidden');
                // Make sure buttons are enabled if geofence is disabled
                const clockInBtn = document.getElementById('btn-clock-in');
                const clockOutBtn = document.getElementById('btn-clock-out');
                if (clockInBtn) clockInBtn.disabled = false;
                if (clockOutBtn) clockOutBtn.disabled = false;
            } else if (!isClockedIn) {
                // Check location once for clock-in; no repeated polling.
                checkLocation();
            } else {
                // Already clocked in — location was verified at clock-in, don't re-check until clock-out.
                document.getElementById('geofence-status').classList.add('hidden');
                const clockOutBtn = document.getElementById('btn-clock-out');
                if (clockOutBtn) clockOutBtn.disabled = false;
            }
        })
        .catch(err => {
            console.error("Failed to load office status", err);
            updateGeofenceUI(false, "Failed to load geofence configuration.", "danger");
        });

    function attendanceAction(action) {
        let payload = {
            _token: '{{ csrf_token() }}'
        };

        // Send location when we have it (recorded server-side), but never block
        // clocking in/out on it — just clock the action through.
        if ((action === 'clock-in' || action === 'clock-out') && currentLat !== null && currentLng !== null) {
            payload.lat = currentLat;
            payload.lng = currentLng;
        }

        fetch(`/attendance/${action}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json().then(data => ({status: res.status, body: data})))
        .then(res => {
            if (res.body.success) {
                window.location.reload();
            } else {
                if (res.status === 403 && res.body.error === 'geofence') {
                    updateGeofenceUI(false, "✗ " + res.body.message, "danger");
                    alert(res.body.message);
                } else {
                    alert(res.body.message || 'Error occurred');
                }
            }
        })
        .catch(err => {
            console.error(err);
            alert('A network error occurred.');
        });
    }
</script>
