<!-- Date & Events Widget (Celebrations / Holidays / Events) -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col h-[460px] dark:bg-slate-800 dark:border-slate-700"
     x-data="celebrationsWidget()">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3 text-slate-700 font-semibold dark:text-slate-200 relative" @click.away="showPicker = false">
            <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
                <i data-lucide="calendar" class="h-5 w-5"></i>
            </div>
            
            <div class="flex items-center gap-1.5 cursor-pointer select-none" @click="showPicker = !showPicker; if (showPicker) initPicker()">
                <span class="text-sm" x-text="displayDate()"></span>
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

    <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden no-scrollbar pr-1 mt-3">
        <div x-show="tab === 'celebrations'">
            <template x-if="todaysCelebrations().length === 0"><p class="text-xs font-semibold text-slate-400 text-center mt-10">No celebrations on this day</p></template>
            <div class="space-y-3">
                <template x-for="c in todaysCelebrations()" :key="c.name + c.type + (c.md || c.date || '')">
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
            outOfOffice: window.__outOfOffice || [],
            oooOpen: false,
            oooSearch: '',
            oooTab: 'leave',
            oooOnDate() { return this.outOfOffice.filter(o => this.current >= o.start && this.current <= o.end); },
            oooFiltered() { const q = this.oooSearch.toLowerCase(); return this.oooOnDate().filter(o => o.name.toLowerCase().includes(q)); },
            tabs: [{ key: 'celebrations', label: 'Celebrations' }, { key: 'holidays', label: 'Holidays' }, { key: 'events', label: 'Events' }],
            celebrations: window.__celebrations || [],
            events: window.__events || [],
            holidays: window.__holidays || [],
            todaysCelebrations() {
                const cur = this.current, md = cur.slice(5), yr = parseInt(cur.slice(0, 4), 10);
                const out = [];
                this.celebrations.forEach(e => {
                    if (e.type === 'new_joiner') { if (e.date === cur) out.push(e); return; }
                    if (e.type === 'probation_completed') { if (e.date === cur) out.push(e); return; }
                    if (e.md !== md) return;                              // birthday / anniversary recur on month-day
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
            monthOf(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { month: 'short' }); },
            dayOf(ds) { return new Date(ds + 'T00:00:00').getDate(); },
            relDate(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); },
            dotColor(type) { return { birthday: 'bg-pink-500', anniversary: 'bg-amber-500', new_joiner: 'bg-emerald-500', probation_completed: 'bg-violet-500' }[type] || 'bg-slate-400'; },
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
