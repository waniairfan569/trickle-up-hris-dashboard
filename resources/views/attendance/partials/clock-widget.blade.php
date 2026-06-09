@php
    $status = app(\App\Services\AttendanceService::class)->getTodayStatus(auth()->user());
    $activeAssignment = auth()->user()->shiftAssignments()->with('shift')->where('assignment_type', 'recurring')->first();
    $expectedStart = ($activeAssignment && $activeAssignment->shift) ? \Carbon\Carbon::parse($activeAssignment->shift->start_time)->format('h:i A') : '--:--';
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col items-center text-center space-y-4" id="clock-widget">
    <div>
        <h3 class="text-lg font-bold text-slate-800" id="live-date"></h3>
        <div class="text-4xl font-black text-brand-600 tracking-tight my-2" id="live-time"></div>
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
            {{ $userTimezone ?? config('app.timezone') }}<span id="live-tz-abbr">{{ isset($userTimezoneAbbr) ? ' · '.$userTimezoneAbbr : '' }}</span>
        </div>
    </div>

    <!-- Geofence Status Badge -->
    <div id="geofence-status" class="hidden w-full text-sm font-medium py-2 px-3 rounded-md mb-2"></div>

    @if(!$status['clock_in'])
        <p class="text-slate-500 text-sm">You haven't clocked in yet. <br> Expected start: <span class="font-semibold">{{ $expectedStart }}</span></p>
        <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="w-full bg-green-600 hover:bg-green-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white font-bold py-3 rounded-lg shadow-md transition transform hover:-translate-y-0.5">
            Clock In
        </button>
    @elseif($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break'])
        <div class="flex flex-col items-center justify-center mb-6">
            <div class="text-3xl font-bold text-slate-800 tracking-tight" id="live-worked-timer">
                0h 0m 0s
            </div>
            <p class="text-slate-500 font-medium text-sm mt-1">Today &middot; Clocked in at {{ $status['clock_in'] }} - <span class="text-brand-600 font-semibold">Ongoing</span></p>
        </div>
        
        <div class="grid grid-cols-2 gap-3 w-full">
            <button id="btn-break-start" onclick="attendanceAction('break/start')" class="bg-amber-100 hover:bg-amber-200 text-amber-700 font-bold py-3 rounded-lg shadow-sm transition">
                Start Break
            </button>
            <button id="btn-clock-out" onclick="attendanceAction('clock-out')" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 rounded-lg shadow-md transition transform hover:-translate-y-0.5 flex items-center justify-center">
                <div class="w-3 h-3 bg-white mr-2"></div> Clock out
            </button>
        </div>
    @elseif($status['is_on_break'])
        <p class="text-amber-600 font-medium bg-amber-50 px-3 py-1 rounded-full">On break since {{ $status['current_break_start'] }}</p>
        <p class="text-slate-500 text-sm mb-2" id="break-timer">Break time counting...</p>
        
        <button id="btn-break-end" onclick="attendanceAction('break/end')" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-md transition transform hover:-translate-y-0.5">
            End Break
        </button>
    @elseif($status['clock_out'])
        <div class="flex flex-col items-center justify-center mb-6">
            <div class="text-3xl font-bold text-slate-800 tracking-tight" id="completed-worked-timer">
                0h 0m 0s
            </div>
            <p class="text-slate-500 font-medium text-sm mt-1">Today &middot; Clocked in at {{ $status['clock_in'] }} - <span class="text-green-600 font-semibold">Completed</span></p>
        </div>
        <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-lg shadow-md transition transform hover:-translate-y-0.5 mt-2">
            Clock In Again
        </button>
    @endif
</div>

<script>
    let workedSeconds = {{ $status['worked_seconds'] ?? 0 }};
    const isClockedIn = {{ ($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break']) ? 'true' : 'false' }};
    
    function formatWorkedTime(totalSeconds) {
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
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
            } else {
                checkLocation();
                setInterval(checkLocation, 30000); // Re-check every 30s
            }
        })
        .catch(err => {
            console.error("Failed to load office status", err);
            updateGeofenceUI(false, "Failed to load geofence configuration.", "danger");
        });

    function attendanceAction(action) {
        if (!confirm(`Are you sure you want to ${action.replace('-', ' ')}?`)) return;
        
        let payload = {
            _token: '{{ csrf_token() }}'
        };

        if (geofenceData && geofenceData.geofence_enabled && (action === 'clock-in' || action === 'clock-out')) {
            if (currentLat === null || currentLng === null) {
                alert("Location is required to clock in/out. Please allow location access.");
                return;
            }
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
