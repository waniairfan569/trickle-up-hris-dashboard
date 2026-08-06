@extends('layouts.hr-app')

@section('title', 'My Dashboard')
@section('breadcrumb', '')

@section('content')
@php
    $auth = auth()->user();
    $status = app(\App\Services\AttendanceService::class)->getTodayStatus($auth);
    $isClockedIn = ($status['clock_in'] ?? null) && !($status['clock_out'] ?? null);

    // Real "needs attention" signals for this employee (documents to sign + own pending leave).
    $signCount = \App\Models\DocumentRequest::where('status', 'in_progress')
        ->whereHas('signers', fn ($s) => $s->where('user_id', $auth->id)->where('status', 'pending'))
        ->with('signers')->get()->filter(fn ($r) => $r->isAwaiting($auth))->count();
    $pendingLeaveCount = \App\Models\TimeOffRequest::where('user_id', $auth->id)->where('status', 'pending')->count();
    $attention = $signCount + $pendingLeaveCount;

    // When the leave balances renew (earliest active leave-year setting).
    $resetDate = null;
    try {
        $rd = \App\Models\LeaveYearSetting::where('is_active', true)->whereNotNull('next_renewal_date')->orderBy('next_renewal_date')->value('next_renewal_date');
        $resetDate = $rd ? \Carbon\Carbon::parse($rd) : null;
    } catch (\Throwable $e) {}
@endphp

<div class="mx-auto space-y-6 pb-12">

    <!-- Greeting -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Hello {{ $auth->first_name }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $isClockedIn ? "You're clocked in" : 'Welcome back' }}@if($attention > 0) and <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $attention }}</span> {{ \Illuminate\Support\Str::plural('thing', $attention) }} need your attention today.@else — here's your day at a glance.@endif
            </p>
        </div>
        <span class="inline-flex items-center gap-2 self-start rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">
            <i data-lucide="calendar" class="h-3.5 w-3.5 text-slate-400"></i> {{ now()->format('l, j F Y') }}
        </span>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Left Column (calendar + balances) — shown AFTER the right column on mobile -->
        <div class="space-y-4 order-last md:order-none">

            <!-- Date & Events Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col h-[460px] dark:bg-slate-800 dark:border-slate-700"
                 x-data="celebrationsWidget()">
                <!-- Date Header -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3 text-slate-700 font-semibold dark:text-slate-200 relative" @click.away="showPicker = false">
                        <div class="h-10 w-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-100/60 dark:border-amber-500/20 flex items-center justify-center text-amber-500">
                            <i data-lucide="calendar" class="h-5 w-5"></i>
                        </div>

                        <div>
                            <div class="flex items-center gap-1.5 cursor-pointer select-none" @click="showPicker = !showPicker; if (showPicker) initPicker()">
                                <span class="text-sm font-bold text-slate-800 dark:text-white" x-text="displayDate()"></span>
                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                            <p class="text-[11px] font-semibold text-teal-600 dark:text-teal-400">Your day at a glance</p>
                        </div>

                        <!-- Custom Calendar Dropdown -->
                        <div x-show="showPicker"
                             x-transition
                             x-cloak
                             class="absolute left-12 top-12 w-64 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 p-4 z-50">
                            <div class="flex items-center justify-between mb-4 px-1">
                                <!-- Prev Month -->
                                <button type="button" @click.stop="changePickerMonth(-1)" class="p-1.5 bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 rounded-lg text-slate-600 dark:text-slate-200 transition">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </button>
                                
                                <!-- Month label -->
                                <span class="text-[10px] font-extrabold uppercase text-slate-800 dark:text-white tracking-wider" x-text="getPickerMonthYearLabel()"></span>
                                
                                <!-- Next Month -->
                                <button type="button" @click.stop="changePickerMonth(1)" class="p-1.5 bg-slate-50 dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 rounded-lg text-slate-600 dark:text-slate-200 transition">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-semibold text-slate-400 dark:text-slate-500 mb-2">
                                <div>Su</div>
                                <div>Mo</div>
                                <div>Tu</div>
                                <div>We</div>
                                <div>Th</div>
                                <div>Fr</div>
                                <div>Sa</div>
                            </div>

                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="d in getPickerDays()" :key="d.dateString">
                                    <button type="button"
                                            @click.stop="selectPickerDate(d.dateString)"
                                            class="h-7 w-7 rounded-lg flex items-center justify-center text-[10px] transition"
                                            :class="[
                                                d.dateString === current 
                                                    ? 'bg-blue-600 text-white font-bold' 
                                                    : (d.currentMonth 
                                                        ? 'text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 font-medium' 
                                                        : 'text-slate-300 dark:text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800')
                                            ]"
                                            x-text="d.day">
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button @click="shift(-1)" class="h-8 w-8 rounded-full border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-50 dark:hover:text-white dark:hover:bg-slate-700 transition"><i data-lucide="arrow-left" class="h-4 w-4"></i></button>
                        <button @click="shift(1)" class="h-8 w-8 rounded-full border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-50 dark:hover:text-white dark:hover:bg-slate-700 transition"><i data-lucide="arrow-right" class="h-4 w-4"></i></button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-5 border-b border-slate-100 dark:border-slate-700">
                    <template x-for="t in tabs" :key="t.key">
                        <button @click="tab = t.key" type="button"
                                class="text-sm pb-2 -mb-px border-b-2 flex items-center gap-1.5 transition"
                                :class="tab === t.key ? 'font-semibold text-slate-800 border-slate-800 dark:text-white dark:border-white' : 'font-medium text-slate-400 border-transparent hover:text-slate-600'">
                            <span x-text="t.label"></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded-md font-bold" :class="tab === t.key ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300'" x-text="count(t.key)"></span>
                        </button>
                    </template>
                </div>

                <!-- Content (scrolls; footer stays put) -->
                <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden no-scrollbar pr-1 mt-3">
                    <!-- Celebrations -->
                    <div x-show="tab === 'celebrations'">
                        <template x-if="todaysCelebrations().length === 0"><p class="text-xs font-semibold text-slate-400 text-center mt-10">No celebrations on this day</p></template>
                        <div class="space-y-2.5">
                            <template x-for="c in todaysCelebrations()" :key="c.name + c.type + (c.md || c.date || '')">
                                <div class="relative flex items-center gap-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 pl-4 pr-3 py-2.5 overflow-hidden">
                                    <div class="absolute left-0 top-2.5 bottom-2.5 w-1 rounded-r-full" :class="dotColor(c.type)"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="c.name"></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate" x-text="c.label"></p>
                                    </div>
                                    <span class="shrink-0 rounded-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 py-0.5 text-[10px] font-bold text-slate-500 dark:text-slate-300" x-text="dayPill()"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Holidays -->
                    <div x-show="tab === 'holidays'" x-cloak>
                        <template x-if="todaysHolidays().length === 0"><p class="text-xs font-semibold text-slate-400 text-center mt-10">No holidays on this day</p></template>
                        <div class="space-y-3">
                            <template x-for="h in todaysHolidays()" :key="h.name + h.date">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center dark:bg-rose-500/10"><i data-lucide="palmtree" class="h-4 w-4"></i></div>
                                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="h.name"></p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Events (upcoming from the selected date) -->
                    <div x-show="tab === 'events'" x-cloak>
                        <template x-if="upcomingEvents().length === 0"><p class="text-xs font-semibold text-slate-400 text-center mt-10">No upcoming events</p></template>
                        <div class="space-y-2.5">
                            <template x-for="e in upcomingEvents()" :key="e.id">
                                <div class="flex items-center gap-3 rounded-lg p-1.5 -mx-1.5" :class="eventOngoing(e) ? 'bg-brand-50 dark:bg-brand-500/10' : ''">
                                    <div class="h-9 w-10 rounded-lg flex flex-col items-center justify-center text-white" :class="dotBg(e.color)">
                                        <span class="text-[9px] font-bold uppercase leading-none" x-text="monthOf(e.date)"></span>
                                        <span class="text-sm font-extrabold leading-none" x-text="dayOf(e.date)"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="e.title"></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                            <span x-text="eventWhen(e)"></span>
                                            <span x-show="e.location" x-text="' · ' + e.location"></span>
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Footer: Out of Office -->
                @include('dashboard.partials.ooo-footer')

                <script>
                window.__celebrations = @json($celebrations);
                window.__events = @json($events);
                window.__holidays = @json($holidays);
                window.__outOfOffice = @json($outOfOffice ?? []);
                function celebrationsWidget() {
                    return {
                        current: '{{ now()->toDateString() }}',
                        tab: 'celebrations',
                        tabs: [{ key: 'celebrations', label: 'Celebrations' }, { key: 'holidays', label: 'Holidays' }, { key: 'events', label: 'Events' }],
                        celebrations: window.__celebrations || [],
                        events: window.__events || [],
                        holidays: window.__holidays || [],
                        outOfOffice: window.__outOfOffice || [],
                        oooOpen: false,
                        oooSearch: '',
                        oooTab: 'leave',
                        oooOnDate() { return this.outOfOffice.filter(o => this.current >= o.start && this.current <= o.end); },
                        oooFiltered() { const q = this.oooSearch.toLowerCase(); return this.oooOnDate().filter(o => o.name.toLowerCase().includes(q)); },
                        todaysCelebrations() {
                            const cur = this.current, md = cur.slice(5), yr = parseInt(cur.slice(0, 4), 10);
                            const out = [];
                            this.celebrations.forEach(e => {
                                if (e.type === 'new_joiner') { if (e.date === cur) out.push(e); return; }
                                if (e.md !== md) return;
                                if (e.type === 'anniversary') {
                                    const years = yr - (e.year || yr);
                                    if (years < 1) return;
                                    out.push({ ...e, label: years + ' year' + (years > 1 ? 's' : '') + ' at the company' });
                                } else { out.push(e); }
                            });
                            return out;
                        },
                        todaysHolidays() { return this.holidays.filter(h => h.date === this.current); },
                        // Show events still running or upcoming (end >= today), one row each.
                        upcomingEvents() { return this.events.filter(e => this.eventEnd(e) >= this.current); },
                        eventEnd(e) { return e.end || e.date; },
                        eventOngoing(e) { return this.current >= e.date && this.current <= this.eventEnd(e); },
                        dm(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); },
                        eventWhen(e) {
                            const end = this.eventEnd(e);
                            if (e.date === end) { return e.date === this.current ? 'Today' : this.relDate(e.date); }
                            const range = this.dm(e.date) + ' – ' + this.dm(end);
                            return this.eventOngoing(e) ? ('In progress · ' + range) : range;
                        },
                        count(key) {
                            if (key === 'celebrations') return this.todaysCelebrations().length;
                            if (key === 'holidays') return this.todaysHolidays().length;
                            return this.upcomingEvents().length;
                        },
                        shift(days) { const d = new Date(this.current + 'T00:00:00'); d.setDate(d.getDate() + days); const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), da = String(d.getDate()).padStart(2, '0'); this.current = `${y}-${m}-${da}`; },
                        displayDate() { return new Date(this.current + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
                        dayPill() {
                            const d = new Date(this.current + 'T00:00:00'), t = new Date();
                            const isToday = d.getFullYear() === t.getFullYear() && d.getMonth() === t.getMonth() && d.getDate() === t.getDate();
                            return isToday ? 'Today' : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
                        },
                        monthOf(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { month: 'short' }); },
                        dayOf(ds) { return new Date(ds + 'T00:00:00').getDate(); },
                        relDate(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); },
                        dotColor(type) { return { birthday: 'bg-pink-500', anniversary: 'bg-amber-500', new_joiner: 'bg-emerald-500' }[type] || 'bg-slate-400'; },
                        dotBg(c) { return { brand: 'bg-brand-500', indigo: 'bg-indigo-500', emerald: 'bg-emerald-500', rose: 'bg-rose-500', sky: 'bg-sky-500' }[c] || 'bg-brand-500'; },
                        showPicker: false,
                        pickerYear: new Date().getFullYear(),
                        pickerMonth: new Date().getMonth(),
                        initPicker() {
                            const d = new Date(this.current + 'T00:00:00');
                            this.pickerYear = d.getFullYear();
                            this.pickerMonth = d.getMonth();
                        },
                        getPickerDays() {
                            const year = this.pickerYear;
                            const month = this.pickerMonth;
                            const firstDayIndex = new Date(year, month, 1).getDay();
                            const totalDays = new Date(year, month + 1, 0).getDate();
                            const prevTotalDays = new Date(year, month, 0).getDate();
                            const days = [];
                            for (let i = firstDayIndex - 1; i >= 0; i--) {
                                const d = prevTotalDays - i;
                                const mStr = String(month === 0 ? 12 : month).padStart(2, '0');
                                const yStr = month === 0 ? year - 1 : year;
                                days.push({
                                    day: d,
                                    currentMonth: false,
                                    dateString: `${yStr}-${mStr}-${String(d).padStart(2, '0')}`
                                });
                            }
                            for (let i = 1; i <= totalDays; i++) {
                                const mStr = String(month + 1).padStart(2, '0');
                                days.push({
                                    day: i,
                                    currentMonth: true,
                                    dateString: `${year}-${mStr}-${String(i).padStart(2, '0')}`
                                });
                            }
                            const remaining = 42 - days.length;
                            for (let i = 1; i <= remaining; i++) {
                                const mStr = String(month === 11 ? 1 : month + 2).padStart(2, '0');
                                const yStr = month === 11 ? year + 1 : year;
                                days.push({
                                    day: i,
                                    currentMonth: false,
                                    dateString: `${yStr}-${mStr}-${String(i).padStart(2, '0')}`
                                });
                            }
                            return days;
                        },
                        selectPickerDate(dateStr) {
                            this.current = dateStr;
                            this.showPicker = false;
                        },
                        changePickerMonth(dir) {
                            this.pickerMonth += dir;
                            if (this.pickerMonth > 11) {
                                this.pickerMonth = 0;
                                this.pickerYear++;
                            } else if (this.pickerMonth < 0) {
                                this.pickerMonth = 11;
                                this.pickerYear--;
                            }
                        },
                        getPickerMonthYearLabel() {
                            const months = ["JANUARY", "FEBRUARY", "MARCH", "APRIL", "MAY", "JUNE", "JULY", "AUGUST", "SEPTEMBER", "OCTOBER", "NOVEMBER", "DECEMBER"];
                            return `${months[this.pickerMonth]} ${this.pickerYear}`;
                        }
                    };
                }
                </script>
            </div>

            <!-- Time-off Balances Widget (shared partial) -->
            @include('dashboard.partials.timeoff-balances-card')

        </div>

        <!-- Right Column (timesheet + code + announcements) — shown FIRST on mobile -->
        <div class="space-y-4 order-first md:order-none">

            <!-- Time Tracking -->
            @php
                $sessions = $status['sessions'] ?? [];
                $currentIn = count($sessions) ? end($sessions)['in'] : $status['clock_in'];
            @endphp
            @php
                // Daily goal = the employee's ASSIGNED SHIFT length for today (e.g. 09:30–18:00 = 8h 30m),
                // not a fixed 8h. Falls back to 8h when no shift is assigned.
                $expectedShift = app(\App\Services\ShiftService::class)->getExpectedTimesForUserOnDate($auth, today());
                $goalSeconds = ($expectedShift && !empty($expectedShift['start']) && !empty($expectedShift['end']))
                    ? max(1, (int) $expectedShift['start']->diffInSeconds($expectedShift['end']))
                    : 28800;
                $goalH = intdiv($goalSeconds, 3600);
                $goalM = intdiv($goalSeconds % 3600, 60);
                $goalLabel = $goalM > 0 ? ($goalH . 'h ' . $goalM . 'm') : ($goalH . 'h');
                $goalPct = min(100, max(0, round(($status['worked_seconds'] ?? 0) / $goalSeconds * 100)));
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <a href="{{ route('attendance.my-history') }}" title="View my attendance" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 text-slate-400 hover:text-brand-600 transition">
                                <i data-lucide="timer" class="h-5 w-5"></i>
                            </a>
                            <div class="min-w-0">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-teal-700 dark:text-teal-400">Timesheet</p>
                                <span id="live-worked-timer-seconds" class="block text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight tabular-nums whitespace-nowrap">0h 0m 0s</span>
                            </div>
                        </div>

                        @if(!$status['clock_in'] || $status['clock_out'])
                            <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="btn-success px-6 whitespace-nowrap shrink-0">
                                <i data-lucide="play" class="h-4 w-4 fill-current"></i>
                                Clock in
                            </button>
                        @elseif($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break'])
                            <button id="btn-clock-out" onclick="attendanceAction('clock-out')" class="btn-dark px-6 whitespace-nowrap shrink-0">
                                <span class="w-2.5 h-2.5 bg-white dark:bg-slate-900 rounded-sm"></span>
                                Clock out
                            </button>
                        @endif
                    </div>

                    <div id="geofence-status" class="hidden text-[10px] font-medium py-1 px-2 rounded mt-3"></div>

                    @if($status['clock_in'])
                        <div class="mt-4">
                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                <div id="worked-progress" class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $goalPct }}%"></div>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-[11px] font-medium text-slate-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $isClockedIn ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $isClockedIn ? 'Ongoing since' : ($status['clock_out'] ? 'Worked from' : 'Since') }} {{ $currentIn }}
                                </span>
                                <span class="font-semibold text-slate-500 dark:text-slate-400"><span id="worked-goal-pct">{{ $goalPct }}</span>% of {{ $goalLabel }} goal</span>
                            </div>
                        </div>
                    @endif

                    {{-- kept (hidden) so the timer JS element lookups stay valid --}}
                    <span id="live-worked-timer" class="hidden"></span><span id="completed-worked-timer" class="hidden"></span>
                </div>
            </div>

            <!-- Quick login-code request (below the timesheet) -->
            @include('partials.code-request-widget')

            <!-- Reusing the full script logic for clock-in/out -->
            <script>
                let workedSeconds = {{ $status['worked_seconds'] ?? 0 }};
                const goalSeconds = {{ $goalSeconds ?? 28800 }};
                const isClockedIn = {{ ($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break']) ? 'true' : 'false' }};
                
                function formatWorkedTime(totalSeconds) {
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    return `${h}h ${m}m`;
                }

                function formatWorkedTimeWithSeconds(totalSeconds) {
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    const s = Math.floor(totalSeconds % 60);
                    return `${h}h ${m}m ${s}s`;
                }

                const liveTimerEl = document.getElementById('live-worked-timer');
                const completedTimerEl = document.getElementById('completed-worked-timer');
                const liveTimerSecondsEl = document.getElementById('live-worked-timer-seconds');
                const workedProgressEl = document.getElementById('worked-progress');
                const workedGoalPctEl = document.getElementById('worked-goal-pct');

                // Progress toward an 8-hour (28,800s) day.
                function updateGoalProgress() {
                    const pct = Math.min(100, Math.max(0, Math.round(workedSeconds / goalSeconds * 100)));
                    if (workedProgressEl) workedProgressEl.style.width = pct + '%';
                    if (workedGoalPctEl) workedGoalPctEl.innerText = pct;
                }

                if (liveTimerSecondsEl) liveTimerSecondsEl.innerText = formatWorkedTimeWithSeconds(workedSeconds);
                if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
                if (completedTimerEl) completedTimerEl.innerText = formatWorkedTime(workedSeconds);
                updateGoalProgress();

                function updateClock() {
                    if (isClockedIn) {
                        workedSeconds++;
                        if (liveTimerSecondsEl) liveTimerSecondsEl.innerText = formatWorkedTimeWithSeconds(workedSeconds);
                        if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
                        updateGoalProgress();
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
                        } else if (!isClockedIn) {
                            // Check location once for clock-in; not again until clock-out.
                            checkLocation();
                        } else {
                            // Already clocked in — don't re-check location.
                            document.getElementById('geofence-status').classList.add('hidden');
                            const clockOutBtn = document.getElementById('btn-clock-out');
                            if (clockOutBtn) clockOutBtn.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error("Failed to load office status", err);
                        updateGeofenceUI(false, "Failed to load geofence.", "danger");
                    });

                function attendanceAction(action) {
                    let payload = { _token: '{{ csrf_token() }}' };

                    // Send location when we have it (recorded server-side), but never
                    // block the action on it — just clock straight through.
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

            <!-- Announcements -->
            @include('partials.announcements')

        </div>
    </div>
</div>
@endsection
