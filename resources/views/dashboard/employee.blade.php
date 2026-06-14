@extends('layouts.hr-app')

@section('title', 'My Dashboard')
@section('breadcrumb', 'Employee Portal')

@section('content')
@php
    $auth = auth()->user();
    

@endphp

<div class="max-w-6xl mx-auto space-y-6 pb-12">
    
    <!-- Greeting -->
    <div>
        <h1 class="text-2xl font-semibold text-slate-800 dark:text-white tracking-tight">Hello {{ $auth->first_name }}!</h1>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Left Column -->
        <div class="space-y-6">
            
            <!-- Date & Events Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col h-[300px] justify-between relative dark:bg-slate-800 dark:border-slate-700">
                <div x-data="celebrationsWidget()">
                    <!-- Date Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2 text-slate-700 font-semibold dark:text-slate-200">
                            <i data-lucide="calendar" class="h-5 w-5 text-slate-400"></i>
                            <div class="relative flex items-center">
                                <span class="cursor-pointer" x-text="displayDate()" @click="$refs.picker.showPicker()"></span>
                                <input type="date" x-ref="picker" x-model="current" class="absolute opacity-0 w-0 h-0">
                            </div>
                            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 cursor-pointer" @click="$refs.picker.showPicker()"></i>
                        </div>
                        <div class="flex items-center gap-2 text-slate-400">
                            <button @click="shift(-1)" class="hover:text-slate-600 transition"><i data-lucide="arrow-left" class="h-4 w-4"></i></button>
                            <button @click="shift(1)" class="hover:text-slate-600 transition"><i data-lucide="arrow-right" class="h-4 w-4"></i></button>
                        </div>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="flex items-center gap-6 border-b border-slate-100 dark:border-slate-700 pb-2">
                        <div class="text-sm font-semibold text-slate-800 border-b-2 border-slate-800 pb-2 -mb-[9px] flex items-center gap-2 dark:text-white dark:border-white">
                            Celebrations <span class="bg-slate-100 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-300" x-text="todays().length">0</span>
                        </div>
                        <div class="text-sm font-medium text-slate-400 flex items-center gap-2 pb-2 -mb-[9px]">
                            Holidays <span class="bg-slate-100 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-300">0</span>
                        </div>
                    </div>
                    
                    <!-- Empty state for the selected day -->
                    <div x-show="todays().length === 0" class="flex flex-col items-center justify-center mt-12 opacity-60">
                        <div class="w-16 h-16 mb-2 relative">
                            <div class="absolute inset-0 flex flex-col gap-2 justify-center items-center">
                                <div class="w-12 h-2 bg-slate-200 rounded-full flex items-center px-1"><div class="w-1 h-1 rounded-full bg-slate-300"></div></div>
                                <div class="w-12 h-2 bg-slate-200 rounded-full flex items-center px-1"><div class="w-1 h-1 rounded-full bg-slate-300"></div></div>
                                <div class="w-12 h-2 bg-slate-200 rounded-full flex items-center px-1"><div class="w-1 h-1 rounded-full bg-slate-300"></div></div>
                            </div>
                        </div>
                        <p class="text-xs font-semibold text-slate-500">No celebrations on this day</p>
                    </div>

                    <!-- Celebrations for the selected day: birthdays, anniversaries, new joiners -->
                    <div x-show="todays().length > 0" class="mt-4 space-y-3 max-h-72 overflow-y-auto pr-1">
                        <template x-for="c in todays()" :key="c.name + c.type + c.date">
                            <div class="flex items-center gap-3">
                                <template x-if="c.avatar">
                                    <img :src="c.avatar" class="h-9 w-9 rounded-xl object-cover ring-1 ring-slate-100 dark:ring-slate-700">
                                </template>
                                <template x-if="!c.avatar">
                                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-brand-400 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold" x-text="c.initials"></div>
                                </template>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="c.name"></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                        <span x-text="icon(c.type)"></span>
                                        <span class="truncate" x-text="c.label"></span>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Footer: Out of Office -->
                <div class="border-t border-slate-100 pt-4 flex items-center justify-between dark:border-slate-700">
                    <div class="text-xs text-slate-600 font-medium dark:text-slate-400">
                        <span class="font-bold text-slate-800 dark:text-white">{{ $outOfOfficeCount }} employee{{ $outOfOfficeCount !== 1 ? 's' : '' }}</span> out of office
                    </div>
                    <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400"></i>
                </div>

                <script>
                window.__celebrations = @json($celebrations);
                function celebrationsWidget() {
                    return {
                        current: '{{ now()->toDateString() }}',
                        events: window.__celebrations || [],
                        todays() { return this.events.filter(e => e.date === this.current); },
                        shift(days) {
                            const d = new Date(this.current + 'T00:00:00');
                            d.setDate(d.getDate() + days);
                            this.current = d.toISOString().slice(0, 10);
                        },
                        displayDate() {
                            const d = new Date(this.current + 'T00:00:00');
                            return d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                        },
                        icon(type) { return type === 'birthday' ? '🎂' : (type === 'anniversary' ? '🏅' : '🟢'); },
                    };
                }
                </script>
            </div>

            <!-- Time-off Balances Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-2 mb-6">
                    <i data-lucide="calendar-check" class="h-5 w-5 text-slate-400"></i>
                    <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your time-off balances</h2>
                </div>
                
                <div class="flex gap-4 overflow-x-auto pb-4 custom-scrollbar">
                    @forelse($timeOffBalances as $index => $b)
                        @php
                            $total = $b->opening_balance + $b->accrued + $b->adjusted + $b->carried_over;
                            $remaining = max(0, $total - $b->used - $b->pending);
                            $policyName = optional($b->policy)->name ?? 'Unpaid Leave';
                            
                            if (stripos($policyName, 'Annual') !== false) {
                                $displayName = 'Planned Leaves';
                            } elseif (stripos($policyName, 'Casual') !== false) {
                                $displayName = 'Unplanned';
                            } else {
                                $displayName = $policyName;
                            }

                            $color = ['bg-cyan-400', 'bg-amber-400', 'bg-rose-400', 'bg-emerald-400', 'bg-indigo-400'][$index % 5];
                        @endphp
                        <div class="flex-shrink-0 w-44 border border-slate-100 rounded-xl p-4 flex flex-col justify-between h-28 relative overflow-hidden dark:border-slate-700">
                            <div class="absolute left-0 top-0 bottom-0 w-1 {{ $color }}"></div>
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 ml-2 truncate" title="{{ $policyName }}">{{ $displayName }}</h3>
                            <div class="ml-2">
                                <span class="text-2xl font-bold text-slate-800 dark:text-white">{{ floatval($remaining) }}</span>
                                <span class="text-[11px] text-slate-500 font-medium">days available</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No time-off balances found.</div>
                    @endforelse
                </div>

                <style>
                    .custom-scrollbar::-webkit-scrollbar {
                        height: 4px;
                    }
                    .custom-scrollbar::-webkit-scrollbar-thumb {
                        background-color: #cbd5e1;
                        border-radius: 4px;
                    }
                    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
                        background-color: #475569;
                    }
                </style>


                <div class="flex gap-2">
                    <a href="{{ route('time-off.create') }}" class="flex-1 bg-brand-600 hover:bg-brand-700 text-slate-900 text-sm font-bold py-3 px-4 rounded-lg flex items-center justify-center gap-2 transition">
                        <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                        Request time off
                    </a>
                    <button class="p-3 border border-slate-200 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition dark:border-slate-600 dark:hover:bg-slate-700">
                        <i data-lucide="calculator" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            
            <!-- Time Tracking (Simple Header Version) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-800 dark:text-white">Time tracking</h2>
                        @php
                            $status = app(\App\Services\AttendanceService::class)->getTodayStatus(auth()->user());
                        @endphp
                        @if($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break'])
                            <div class="text-sm font-medium text-slate-500 mt-1">
                                Today &middot; <span id="live-worked-timer" class="font-bold text-slate-700">0h 0m</span> in total: <span class="text-brand-600 font-semibold">{{ \Carbon\Carbon::parse($status['clock_in'])->format('h:i A') }} - Ongoing</span>
                            </div>
                        @elseif($status['clock_out'])
                            <div class="text-sm font-medium text-slate-500 mt-1">
                                Today &middot; <span id="completed-worked-timer" class="font-bold text-slate-700">0h 0m</span> in total: <span class="text-green-600 font-semibold">{{ \Carbon\Carbon::parse($status['clock_in'])->format('h:i A') }} - Completed</span>
                            </div>
                        @endif
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @if(!$status['clock_in'] || $status['clock_out'])
                            <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="bg-brand-600 hover:bg-brand-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-slate-900 text-sm font-bold py-2.5 px-5 rounded-lg flex items-center gap-2 transition">
                                <div class="w-2 h-2 bg-slate-900 rounded-sm"></div>
                                Clock in
                            </button>
                        @elseif($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break'])
                            <button id="btn-clock-out" onclick="attendanceAction('clock-out')" class="bg-slate-800 hover:bg-slate-900 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-sm font-bold py-2.5 px-5 rounded-lg flex items-center gap-2 transition">
                                <div class="w-2 h-2 bg-white rounded-sm"></div>
                                Clock out
                            </button>
                        @endif
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400"></i>
                    </div>
                </div>
                <div id="geofence-status" class="hidden w-full text-[10px] font-medium py-1 px-2 rounded mt-3"></div>
            </div>
            
            <!-- Reusing the full script logic for clock-in/out -->
            <script>
                let workedSeconds = {{ $status['worked_seconds'] ?? 0 }};
                const isClockedIn = {{ ($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break']) ? 'true' : 'false' }};
                
                function formatWorkedTime(totalSeconds) {
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    return `${h}h ${m}m`;
                }

                const liveTimerEl = document.getElementById('live-worked-timer');
                const completedTimerEl = document.getElementById('completed-worked-timer');
                if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
                if (completedTimerEl) completedTimerEl.innerText = formatWorkedTime(workedSeconds);

                function updateClock() {
                    if (isClockedIn) {
                        workedSeconds++;
                        if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
                    }
                }
                setInterval(updateClock, 1000);
                let geofenceData = null;
                let currentLat = null;
                let currentLng = null;

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
                        updateGeofenceUI(false, "Geolocation not supported.", "danger");
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            currentLat = position.coords.latitude;
                            currentLng = position.coords.longitude;
                            
                            let isAllowed = false;

                            for (let office of geofenceData.office_locations) {
                                if (office.allow_remote) {
                                    isAllowed = true;
                                    break;
                                }
                                const dist = haversine(currentLat, currentLng, office.lat, office.lng);
                                if (dist <= office.radius) {
                                    isAllowed = true;
                                    updateGeofenceUI(true, `✓ ${office.name}`, "success");
                                    return;
                                }
                            }

                            if (!isAllowed) {
                                updateGeofenceUI(false, "✗ Outside office zone.", "danger");
                            }
                        },
                        (error) => {
                            updateGeofenceUI(false, "✗ Location access required.", "danger");
                        },
                        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                    );
                }

                updateGeofenceUI(false, "Checking location... ⏳", "info");
                
                fetch('/attendance/office-status')
                    .then(res => res.json())
                    .then(data => {
                        geofenceData = data;
                        if (!data.geofence_enabled) {
                            document.getElementById('geofence-status').classList.add('hidden');
                            const clockInBtn = document.getElementById('btn-clock-in');
                            const clockOutBtn = document.getElementById('btn-clock-out');
                            if (clockInBtn) clockInBtn.disabled = false;
                            if (clockOutBtn) clockOutBtn.disabled = false;
                        } else {
                            checkLocation();
                        }
                    })
                    .catch(err => {
                        console.error("Failed to load office status", err);
                        updateGeofenceUI(false, "Failed to load geofence.", "danger");
                    });

                function attendanceAction(action) {
                    if (!confirm(`Are you sure you want to ${action.replace('-', ' ')}?`)) return;
                    
                    let payload = { _token: '{{ csrf_token() }}' };

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

            <!-- To-dos Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-2 mb-6">
                    <i data-lucide="check-circle" class="h-5 w-5 text-slate-400"></i>
                    <h2 class="text-base font-semibold text-slate-800 dark:text-white">To-dos</h2>
                </div>
                
                <div class="bg-slate-50 rounded-xl p-8 flex flex-col items-center justify-center text-center dark:bg-slate-800/50">
                    <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mb-4 dark:bg-emerald-500/10">
                        <i data-lucide="check" class="h-6 w-6"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800 mb-1 dark:text-white">All done for today</h3>
                    <p class="text-[11px] text-slate-500 max-w-xs">No pending items. If anything comes up we will display it here for you to take action.</p>
                </div>
            </div>

            <!-- Upcoming Time Off Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-2 mb-6">
                    <i data-lucide="calendar-clock" class="h-5 w-5 text-slate-400"></i>
                    <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your upcoming time off</h2>
                </div>
                
                <div class="bg-slate-50 rounded-xl p-8 flex flex-col items-center justify-center text-center dark:bg-slate-800/50">
                    @if($upcomingTimeOff->isEmpty())
                        <div class="w-16 h-16 mb-2 relative opacity-60">
                            <!-- Faux lines -->
                            <div class="absolute inset-0 flex flex-col gap-2 justify-center items-center">
                                <div class="w-10 h-2 bg-slate-200 rounded-full flex items-center px-1"></div>
                                <div class="w-12 h-2 bg-slate-200 rounded-full flex items-center px-1"></div>
                                <div class="w-8 h-2 bg-slate-200 rounded-full flex items-center px-1"></div>
                            </div>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800 mb-1 dark:text-white">No upcoming time off</h3>
                        <p class="text-[11px] text-slate-500 max-w-xs">There are no upcoming time-off requests.</p>
                    @else
                        <ul class="w-full text-left space-y-3">
                            @foreach($upcomingTimeOff as $req)
                                <li class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                                    {{ \Carbon\Carbon::parse($req->start_date)->format('M d') }} - {{ \Carbon\Carbon::parse($req->end_date)->format('M d, Y') }} 
                                    <span class="text-xs text-slate-400 font-normal">({{ optional($req->policy)->name }})</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
