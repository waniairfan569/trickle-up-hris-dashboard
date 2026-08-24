@extends('layouts.hr-app')

@section('title', 'Company Calendar')
@section('breadcrumb', 'Calendar')

@section('content')
<style>
    [x-cloak]{display:none!important}
    .fc .fc-daygrid-event{cursor:pointer}
    .fc .fc-toolbar-title{font-size:1rem;font-weight:800}
    .fc .fc-button{background:#fff;border-color:#e2e8f0;color:#334155;font-weight:700;text-transform:capitalize}
    .fc .fc-button-primary:not(:disabled).fc-button-active{background:#F5C800;border-color:#F5C800;color:#111}
    .dark .fc{--fc-border-color:#334155;--fc-page-bg-color:transparent;color:#e2e8f0}
    .dark .fc .fc-button{background:#1e293b;border-color:#334155;color:#e2e8f0}
    .dark .fc .fc-col-header-cell-cushion,.dark .fc .fc-daygrid-day-number{color:#94a3b8}
</style>

<div class="space-y-6" x-data="empCalendar()">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="calendar-days" class="h-6 w-6 text-brand-500"></i> Company Calendar
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Upcoming company events shared with you.</p>
    </div>

    {{-- Pinned / upcoming highlights --}}
    @if($pinned->isNotEmpty())
        <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-5 dark:border-brand-500/30 dark:bg-brand-500/5">
            <div class="flex items-center gap-2 mb-3">
                <i data-lucide="pin" class="h-4 w-4 text-brand-600 dark:text-brand-400"></i>
                <h2 class="text-sm font-extrabold text-slate-800 dark:text-white">Upcoming</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($pinned as $event)
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3.5 shadow-sm dark:bg-slate-800">
                        <span class="mt-1 h-3 w-3 rounded-full shrink-0" style="background:{{ $event->color_hex }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $event->title }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $event->date->format('d M') }}@if($event->is_multi_day)–{{ $event->end_date->format('d M') }}@endif
                                @if($event->location) · {{ $event->location }} @endif
                            </p>
                            @if($event->description)<p class="text-xs text-slate-400 mt-0.5 line-clamp-1">{{ $event->description }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Calendar --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-5 dark:bg-slate-800 dark:border-slate-700">
        <div id="employee-calendar"></div>
    </div>

    {{-- Read-only event detail modal --}}
    <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="modal.open = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="px-6 py-5">
                <div class="flex items-start gap-2.5">
                    <span class="mt-1 h-3 w-3 rounded-full shrink-0" :style="`background:${modal.color}`"></span>
                    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white" x-text="modal.title"></h3>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" x-text="modal.dates"></p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5" x-show="modal.location"><i data-lucide="map-pin" class="h-3.5 w-3.5 inline -mt-0.5"></i> <span x-text="modal.location"></span></p>
                <p class="text-sm text-slate-600 dark:text-slate-300 mt-3 whitespace-pre-line" x-show="modal.description" x-text="modal.description"></p>
            </div>
            <button type="button" @click="modal.open=false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
    </div>

    {{-- Day chooser (a date with more than one event) --}}
    <div x-show="day.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="day.open = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" x-text="day.label"></h3>
                <button type="button" @click="day.open = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="p-2 max-h-[60vh] overflow-y-auto">
                <template x-for="ev in day.events" :key="ev.id">
                    <button type="button" @click="openFromDay(ev.id)" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="`background:${ev.color}`"></span>
                        <span class="flex-1 text-sm font-bold text-slate-800 dark:text-white truncate" x-text="ev.title"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
    function empCalendar() {
        return {
            calendar: null,
            modal: { open: false, title: '', dates: '', location: '', description: '', color: '#F5C800' },
            day: { open: false, label: '', events: [] },
            init() { this.$nextTick(() => this.mount()); },
            mount() {
                const el = document.getElementById('employee-calendar');
                if (!el || this.calendar || typeof FullCalendar === 'undefined') return;
                this.calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth', height: 'auto', firstDay: 1,
                    events: '{{ route('events.employee-calendar-data') }}',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek,listMonth' },
                    eventClick: (info) => { info.jsEvent.preventDefault(); this.openEvent(info.event); },
                    dateClick: (info) => { this.openDay(info.dateStr); },
                });
                this.calendar.render();
            },
            openEvent(ev) {
                const p = ev.extendedProps;
                const fmt = d => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                let dates = ev.start ? fmt(ev.start) : '';
                if (p.end_display) { const ed = fmt(p.end_display); if (ed !== dates) dates += ' – ' + ed; }
                this.modal = { open: true, title: ev.title, dates, location: p.location || '', description: p.description || '', color: ev.backgroundColor || '#F5C800' };
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },
            // Clicking a day cell: one event → its detail, several → a chooser.
            openDay(dateStr) {
                if (!this.calendar) return;
                const onDay = this.calendar.getEvents().filter(ev => {
                    const s = ev.startStr.slice(0, 10);
                    const eEx = ev.endStr ? ev.endStr.slice(0, 10) : null;
                    return eEx ? (dateStr >= s && dateStr < eEx) : (dateStr === s);
                });
                if (onDay.length === 0) return;
                if (onDay.length === 1) { this.openEvent(onDay[0]); return; }
                this.day = {
                    open: true,
                    label: new Date(dateStr).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
                    events: onDay.map(ev => ({ id: ev.id, title: ev.title, color: ev.backgroundColor || '#F5C800' })),
                };
                this.$nextTick(() => window.lucide && window.lucide.createIcons());
            },
            openFromDay(id) { this.day.open = false; const fc = this.calendar.getEventById(id); if (fc) this.openEvent(fc); },
        };
    }
</script>
@endsection
