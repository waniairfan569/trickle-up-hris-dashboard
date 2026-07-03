@extends('layouts.hr-app')

@section('title', 'My Dashboard')
@section('breadcrumb', 'Employee Portal')

@section('content')
@php
    $auth = auth()->user();
    

@endphp

<div class="mx-auto space-y-6 pb-12">
    
    <!-- Greeting -->
    <div>
        <h1 class="text-2xl font-semibold text-slate-800 dark:text-white tracking-tight">Hello {{ $auth->first_name }}!</h1>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        <!-- Left Column -->
        <div class="space-y-4">
            
            <!-- Date & Events Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col h-[460px] dark:bg-slate-800 dark:border-slate-700"
                 x-data="celebrationsWidget()">
                <!-- Date Header -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3 text-slate-700 font-semibold dark:text-slate-200 relative" @click.away="showPicker = false">
                        <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
                            <i data-lucide="calendar" class="h-5 w-5"></i>
                        </div>
                        
                        <div class="flex items-center gap-1.5 cursor-pointer select-none" @click="showPicker = !showPicker; if (showPicker) initPicker()">
                            <span class="text-base" x-text="displayDate()"></span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
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
                    <div class="flex items-center gap-2 text-slate-400">
                        <button @click="shift(-1)" class="hover:text-slate-600 transition"><i data-lucide="arrow-left" class="h-4 w-4"></i></button>
                        <button @click="shift(1)" class="hover:text-slate-600 transition"><i data-lucide="arrow-right" class="h-4 w-4"></i></button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-5 border-b border-slate-100 dark:border-slate-700">
                    <template x-for="t in tabs" :key="t.key">
                        <button @click="tab = t.key" type="button"
                                class="text-sm pb-2 -mb-px border-b-2 flex items-center gap-1.5 transition"
                                :class="tab === t.key ? 'font-semibold text-slate-800 border-slate-800 dark:text-white dark:border-white' : 'font-medium text-slate-400 border-transparent hover:text-slate-600'">
                            <span x-text="t.label"></span>
                            <span class="bg-slate-100 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-300" x-text="count(t.key)"></span>
                        </button>
                    </template>
                </div>

                <!-- Content (scrolls; footer stays put) -->
                <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden no-scrollbar pr-1 mt-3">
                    <!-- Celebrations -->
                    <div x-show="tab === 'celebrations'">
                        <template x-if="todaysCelebrations().length === 0"><p class="text-xs font-semibold text-slate-400 text-center mt-10">No celebrations on this day</p></template>
                        <div class="space-y-3">
                            <template x-for="c in todaysCelebrations()" :key="c.name + c.type + c.date">
                                <div class="flex items-center gap-3">
                                    <template x-if="c.avatar"><img :src="c.avatar" class="h-9 w-9 rounded-xl object-cover ring-1 ring-slate-100 dark:ring-slate-700"></template>
                                    <template x-if="!c.avatar"><div class="h-9 w-9 rounded-xl bg-gradient-to-br from-brand-400 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold" x-text="c.initials"></div></template>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="c.name"></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full flex-shrink-0" :class="dotColor(c.type)"></span><span class="truncate" x-text="c.label"></span></p>
                                    </div>
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
                            <template x-for="e in upcomingEvents()" :key="e.id + e.date">
                                <div class="flex items-center gap-3 rounded-lg p-1.5 -mx-1.5" :class="e.date === current ? 'bg-brand-50 dark:bg-brand-500/10' : ''">
                                    <div class="h-9 w-10 rounded-lg flex flex-col items-center justify-center text-white" :class="dotBg(e.color)">
                                        <span class="text-[9px] font-bold uppercase leading-none" x-text="monthOf(e.date)"></span>
                                        <span class="text-sm font-extrabold leading-none" x-text="dayOf(e.date)"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="e.title"></p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                            <span x-text="e.date === current ? 'Today' : relDate(e.date)"></span>
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
                        todaysCelebrations() { return this.celebrations.filter(e => e.date === this.current); },
                        todaysHolidays() { return this.holidays.filter(h => h.date === this.current); },
                        upcomingEvents() { return this.events.filter(e => e.date >= this.current); },
                        count(key) {
                            if (key === 'celebrations') return this.todaysCelebrations().length;
                            if (key === 'holidays') return this.todaysHolidays().length;
                            return this.upcomingEvents().length;
                        },
                        shift(days) { const d = new Date(this.current + 'T00:00:00'); d.setDate(d.getDate() + days); const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), da = String(d.getDate()).padStart(2, '0'); this.current = `${y}-${m}-${da}`; },
                        displayDate() { return new Date(this.current + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
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

            <!-- Time-off Balances Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-3 mb-6">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
                        <i data-lucide="calendar-check" class="h-5 w-5"></i>
                    </div>
                    <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your time-off balances</h2>
                </div>
                
                <div x-data="balanceSlider({{ $timeOffBalances->count() }})" class="relative">
                    <!-- Left Arrow -->
                    <button type="button" @click="scrollPrev()" x-show="active > 0" class="absolute -left-2 top-[56px] -translate-y-1/2 z-10 p-1.5 bg-slate-50 dark:bg-slate-700 border border-slate-100 dark:border-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition flex items-center justify-center shadow-sm">
                        <i data-lucide="chevron-left" class="h-4 w-4 text-slate-600 dark:text-slate-200"></i>
                    </button>

                    <div x-ref="slider" @scroll.debounce.50ms="onScroll()" class="flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-1 scroll-smooth">
                        @forelse($timeOffBalances as $index => $b)
                            @php
                                $total = $b->opening_balance + $b->accrued + $b->adjusted + $b->carried_over;
                                $remaining = max(0, $total - $b->used - $b->pending);
                                $policyName = optional($b->policy)->name ?? 'Unpaid Leave';

                                if (stripos($policyName, 'Annual') !== false) {
                                    $displayName = 'Planned Leaves';
                                } elseif (stripos($policyName, 'Casual') !== false) {
                                    $displayName = 'Unplanned Leaves';
                                } else {
                                    $displayName = $policyName;
                                }

                                $color = ['bg-cyan-300', 'bg-amber-300', 'bg-rose-300', 'bg-emerald-300', 'bg-indigo-300'][$index % 5];
                                $leaveUnit = optional(auth()->user()->company)->leave_unit ?? 'days';
                                $isUnpaid = !$b->policy || !$b->policy->is_paid;
                            @endphp
                            <div class="snap-start flex-shrink-0 w-[calc(50%-8px)] min-w-[190px] border border-slate-100 rounded-xl p-4 flex flex-col justify-between h-28 relative overflow-hidden dark:border-slate-700 bg-white dark:bg-slate-800">
                                <div class="absolute left-3 top-3 bottom-3 w-1 rounded-full {{ $color }}"></div>
                                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 pl-2 truncate" title="{{ $policyName }}">{{ $displayName }}</h3>
                                <div class="pl-2">
                                    <span class="text-2xl font-bold text-slate-800 dark:text-white">
                                        @if($isUnpaid)
                                            &infin;
                                        @else
                                            {{ floatval($remaining) }}
                                        @endif
                                    </span>
                                    <span class="text-[11px] text-slate-500 font-medium">{{ $leaveUnit === 'hours' ? 'hours available' : 'days available' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-sm text-slate-500">No time-off balances found.</div>
                        @endforelse
                    </div>

                    <!-- Right Arrow -->
                    <button type="button" @click="scrollNext()" x-show="active < count - 2" class="absolute -right-2 top-[56px] -translate-y-1/2 z-10 p-1.5 bg-slate-50 dark:bg-slate-700 border border-slate-100 dark:border-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition flex items-center justify-center shadow-sm">
                        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-600 dark:text-slate-200"></i>
                    </button>

                    <!-- Navigation dots -->
                    <div x-show="count > 1" class="flex justify-center gap-1.5 mt-4 mb-1">
                        <template x-for="i in count" :key="i">
                            <button type="button" @click="goTo(i - 1)" :aria-label="'Go to slide ' + i"
                                    :class="active === (i - 1) ? 'bg-slate-800 dark:bg-slate-100 w-4' : 'bg-slate-300 dark:bg-slate-600 w-1.5 hover:bg-slate-400'"
                                    class="h-1.5 rounded-full transition-all duration-200"></button>
                        </template>
                    </div>
                </div>

                <style>
                    .no-scrollbar::-webkit-scrollbar { display: none; }
                    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
                </style>
                <script>
                    function balanceSlider(count) {
                        return {
                            count: count,
                            active: 0,
                            onScroll() {
                                const s = this.$refs.slider;
                                if (!s.children.length) return;
                                const base = s.children[0].offsetLeft;
                                let best = 0, bestDist = Infinity;
                                Array.from(s.children).forEach((c, i) => {
                                    const dist = Math.abs((c.offsetLeft - base) - s.scrollLeft);
                                    if (dist < bestDist) { bestDist = dist; best = i; }
                                });
                                this.active = best;
                            },
                            goTo(i) {
                                const s = this.$refs.slider;
                                const c = s.children[i];
                                if (c) s.scrollTo({ left: c.offsetLeft - s.children[0].offsetLeft, behavior: 'smooth' });
                            },
                            scrollNext() {
                                if (this.active < this.count - 1) {
                                    this.goTo(this.active + 1);
                                }
                            },
                            scrollPrev() {
                                if (this.active > 0) {
                                    this.goTo(this.active - 1);
                                }
                            }
                        };
                    }
                </script>


                <div class="flex gap-2 mt-5">
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
        <div class="space-y-4">

            <!-- Quick login-code request -->
            @include('partials.code-request-widget')

            <!-- Time Tracking (Simple Header Version) -->
            @php
                $status = app(\App\Services\AttendanceService::class)->getTodayStatus(auth()->user());
                $sessions = $status['sessions'] ?? [];
                $currentIn = count($sessions) ? end($sessions)['in'] : $status['clock_in'];
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 flex flex-col dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                <!-- Top Section -->
                <div class="p-6">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('attendance.my-history') }}" class="text-base font-bold text-teal-800 dark:text-teal-400 hover:text-teal-955 dark:hover:text-teal-300 transition">
                            Timesheet
                        </a>

                        <div class="flex-1 flex items-center gap-3">
                            <!-- Timer Pill Container -->
                            <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg px-6 py-2.5 flex items-center justify-center flex-1 h-11">
                                <span id="live-worked-timer-seconds" class="text-base font-semibold text-slate-800 dark:text-slate-200 tracking-tight">0h 0m 0s</span>
                            </div>

                            @if(!$status['clock_in'] || $status['clock_out'])
                                <button id="btn-clock-in" onclick="attendanceAction('clock-in')" class="bg-green-700 hover:bg-green-800 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-sm font-semibold h-11 px-6 rounded-lg inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition">
                                    <i data-lucide="play" class="h-4 w-4 fill-current"></i>
                                    Clock in
                                </button>
                            @elseif($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break'])
                                <button id="btn-clock-out" onclick="attendanceAction('clock-out')" class="bg-[#2d3139] hover:bg-[#1f2229] disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-sm font-semibold h-11 px-6 rounded-lg inline-flex items-center justify-center gap-2 whitespace-nowrap shrink-0 transition">
                                    <span class="w-2.5 h-2.5 bg-white rounded-sm"></span>
                                    Clock out
                                </button>
                            @endif
                        </div>
                    </div>
                    
                    <div id="geofence-status" class="hidden text-[10px] font-medium py-1 px-2 rounded mt-3"></div>
                </div>

                <!-- Footer Section -->
                @if($status['clock_in'] && !$status['clock_out'] && !$status['is_on_break'])
                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/50 text-sm font-medium text-slate-500">
                        Today &middot; <span id="live-worked-timer" class="font-bold text-slate-700 dark:text-slate-200">0h 0m</span> in total: <span class="text-slate-600 dark:text-slate-400 font-semibold">{{ $currentIn }} – Ongoing</span>
                    </div>
                @elseif($status['clock_out'])
                    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/50 text-sm font-medium text-slate-500">
                        Today &middot; <span id="completed-worked-timer" class="font-bold text-slate-700 dark:text-slate-200">0h 0m</span> in total: <span class="text-slate-600 dark:text-slate-400 font-semibold">{{ $currentIn }} – Completed</span>
                    </div>
                @endif
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

                function formatWorkedTimeWithSeconds(totalSeconds) {
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    const s = Math.floor(totalSeconds % 60);
                    return `${h}h ${m}m ${s}s`;
                }

                const liveTimerEl = document.getElementById('live-worked-timer');
                const completedTimerEl = document.getElementById('completed-worked-timer');
                const liveTimerSecondsEl = document.getElementById('live-worked-timer-seconds');

                if (liveTimerSecondsEl) liveTimerSecondsEl.innerText = formatWorkedTimeWithSeconds(workedSeconds);
                if (liveTimerEl) liveTimerEl.innerText = formatWorkedTime(workedSeconds);
                if (completedTimerEl) completedTimerEl.innerText = formatWorkedTime(workedSeconds);

                function updateClock() {
                    if (isClockedIn) {
                        workedSeconds++;
                        if (liveTimerSecondsEl) liveTimerSecondsEl.innerText = formatWorkedTimeWithSeconds(workedSeconds);
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

            <!-- To-dos Widget -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-3 mb-6">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
                        <i data-lucide="check-circle" class="h-5 w-5"></i>
                    </div>
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
            <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col h-[314px] dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-3 mb-6">
                    <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
                        <i data-lucide="calendar-clock" class="h-5 w-5"></i>
                    </div>
                    <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your upcoming time off</h2>
                </div>
                
                <div class="bg-slate-50 rounded-xl p-8 flex flex-col items-center justify-center text-center dark:bg-slate-800/50 flex-1">
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
