<!-- Date & Events Widget (Celebrations / Holidays / Events) -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col h-[300px] dark:bg-slate-800 dark:border-slate-700"
     x-data="celebrationsWidget()">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2 text-slate-700 font-semibold dark:text-slate-200">
            <i data-lucide="calendar" class="h-5 w-5 text-slate-400"></i>
            <div class="relative flex items-center">
                <span class="cursor-pointer text-sm" x-text="displayDate()" @click="$refs.picker.showPicker()"></span>
                <input type="date" x-ref="picker" x-model="current" class="absolute opacity-0 w-0 h-0">
            </div>
            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 cursor-pointer" @click="$refs.picker.showPicker()"></i>
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

    <div class="flex-1 min-h-0 overflow-y-auto pr-1 mt-3">
        <div x-show="tab === 'celebrations'">
            <template x-if="todaysCelebrations().length === 0"><p class="text-xs font-semibold text-slate-400 text-center mt-10">No celebrations on this day</p></template>
            <div class="space-y-3">
                <template x-for="c in todaysCelebrations()" :key="c.name + c.type + c.date">
                    <div class="flex items-center gap-3">
                        <template x-if="c.avatar"><img :src="c.avatar" class="h-9 w-9 rounded-xl object-cover ring-1 ring-slate-100 dark:ring-slate-700"></template>
                        <template x-if="!c.avatar"><div class="h-9 w-9 rounded-xl bg-gradient-to-br from-brand-400 to-indigo-500 flex items-center justify-center text-white text-[11px] font-bold" x-text="c.initials"></div></template>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="c.name"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1"><span x-text="icon(c.type)"></span><span class="truncate" x-text="c.label"></span></p>
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

    <div class="border-t border-slate-100 pt-3 mt-3 flex items-center justify-between dark:border-slate-700">
        <div class="text-xs text-slate-600 font-medium dark:text-slate-400">
            <span class="font-bold text-slate-800 dark:text-white">{{ $outOfOfficeCount }} employee{{ $outOfOfficeCount !== 1 ? 's' : '' }}</span> out of office
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400"></i>
    </div>

    <script>
    window.__celebrations = @json($celebrations);
    window.__events = @json($events);
    window.__holidays = @json($holidays);
    function celebrationsWidget() {
        return {
            current: '{{ now()->toDateString() }}',
            tab: 'celebrations',
            tabs: [{ key: 'celebrations', label: 'Celebrations' }, { key: 'holidays', label: 'Holidays' }, { key: 'events', label: 'Events' }],
            celebrations: window.__celebrations || [],
            events: window.__events || [],
            holidays: window.__holidays || [],
            todaysCelebrations() { return this.celebrations.filter(e => e.date === this.current); },
            todaysHolidays() { return this.holidays.filter(h => h.date === this.current); },
            upcomingEvents() { return this.events.filter(e => e.date >= this.current); },
            count(key) {
                if (key === 'celebrations') return this.todaysCelebrations().length;
                if (key === 'holidays') return this.todaysHolidays().length;
                return this.events.filter(e => e.date === this.current).length;
            },
            shift(days) { const d = new Date(this.current + 'T00:00:00'); d.setDate(d.getDate() + days); const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), da = String(d.getDate()).padStart(2, '0'); this.current = `${y}-${m}-${da}`; },
            displayDate() { return new Date(this.current + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); },
            monthOf(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { month: 'short' }); },
            dayOf(ds) { return new Date(ds + 'T00:00:00').getDate(); },
            relDate(ds) { return new Date(ds + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }); },
            icon(type) { return type === 'birthday' ? '🎂' : (type === 'anniversary' ? '🏅' : '🟢'); },
            dotBg(c) { return { brand: 'bg-brand-500', indigo: 'bg-indigo-500', emerald: 'bg-emerald-500', rose: 'bg-rose-500', sky: 'bg-sky-500' }[c] || 'bg-brand-500'; },
        };
    }
    </script>
</div>
