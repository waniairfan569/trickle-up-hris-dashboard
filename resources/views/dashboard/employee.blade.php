@extends('layouts.hr-app')

@section('title', 'My Dashboard')
@section('breadcrumb', '')

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

    // A gentle daily nudge — stable for the whole day.
    $quotes = [
        'Small steps every day lead to big results.',
        'Progress, not perfection.',
        'Great things are built one day at a time.',
        'Focus on what matters most today.',
        'Consistency beats intensity.',
        'Make today count.',
        'A little progress each day adds up.',
    ];
    $quote = $quotes[now()->dayOfYear % count($quotes)];
@endphp

{{-- Greeting shown in the top bar (see layouts/hr-app header) --}}
@section('greeting')
    <div class="min-w-0 leading-tight">
        <p class="flex items-center gap-1.5 text-sm font-bold text-slate-900 dark:text-white truncate"><span>👋</span> Hello {{ $auth->first_name }}</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $isClockedIn ? "You're clocked in" : 'Welcome back' }}@if($attention > 0) and <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $attention }}</span> {{ \Illuminate\Support\Str::plural('thing', $attention) }} need your attention today.@else — here's your day at a glance.@endif</p>
    </div>
@endsection

@section('content')
<div class="mx-auto pb-12">

    <!-- Unread-announcement bar + auto-popup -->
    @include('partials.announcement-alert')

    <!-- Employees waiting for a login code — only for people a super admin delegated code-sending to -->
    @if(auth()->user()->can_send_codes && plan_allows('code_requests'))
        @include('partials.code-request-hr-banner')
    @endif

    <div class="space-y-6">

    <!-- Day at a glance -->
    <div class="space-y-4" x-data="celebrationsWidget()">

        <!-- Date bar -->
        <div class="relative flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/70 bg-white px-5 py-4 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                    <div class="flex items-center gap-3 text-slate-700 font-semibold dark:text-slate-200 relative" @click.away="showPicker = false">
                        <div class="h-11 w-11 rounded-xl bg-indigo-100 dark:bg-indigo-500/15 flex items-center justify-center text-indigo-500 dark:text-indigo-400">
                            <i data-lucide="calendar" class="h-5 w-5"></i>
                        </div>

                        <div>
                            <div class="flex items-center gap-1.5 cursor-pointer select-none" @click="showPicker = !showPicker; if (showPicker) initPicker()">
                                <span class="text-base font-bold text-slate-800 dark:text-white" x-text="displayDate()"></span>
                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400"><i data-lucide="sun" class="h-3.5 w-3.5 text-amber-500"></i> {{ $quote }}</p>
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
                        <a href="{{ route('events.employee-calendar') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50 dark:bg-slate-800 dark:border-slate-700 dark:text-indigo-400 dark:hover:bg-slate-700">
                            <i data-lucide="calendar-days" class="h-4 w-4"></i> View Calendar <i data-lucide="chevron-right" class="h-4 w-4"></i>
                        </a>
                    </div>
        </div>

        <!-- Three cards: Celebrations · Announcements · Upcoming events -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

            <!-- Celebrations -->
            <div class="relative overflow-hidden flex flex-col h-[300px] rounded-2xl border border-slate-200/70 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <div class="relative flex items-center gap-2.5 px-5 pt-5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-pink-100 text-pink-500 dark:bg-pink-500/15 dark:text-pink-400"><i data-lucide="party-popper" class="h-5 w-5"></i></span>
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Celebrations</h3>
                    <span class="ml-auto grid h-6 min-w-6 place-items-center rounded-full bg-slate-100 px-1.5 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300" x-text="count('celebrations')"></span>
                </div>
                <div class="relative flex-1 min-h-0 overflow-y-auto overflow-x-hidden no-scrollbar px-5 py-4">
                    <template x-if="todaysCelebrations().length === 0">
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <span class="mb-3 grid h-16 w-16 place-items-center rounded-full bg-pink-100/70 text-pink-400 dark:bg-pink-500/10"><i data-lucide="party-popper" class="h-7 w-7"></i></span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">No celebrations on this day</p>
                            <p class="mt-1 max-w-[210px] text-xs text-slate-400">Check back soon for birthdays, work anniversaries and more!</p>
                        </div>
                    </template>
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
            </div>

            <!-- Announcements -->
            <div class="relative overflow-hidden flex flex-col h-[300px] rounded-2xl border border-slate-200/70 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <div class="relative flex items-center gap-2.5 px-5 pt-5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"><i data-lucide="megaphone" class="h-5 w-5"></i></span>
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Announcements</h3>
                    <span class="ml-auto grid h-6 min-w-6 place-items-center rounded-full bg-slate-100 px-1.5 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300" x-text="count('announcements')"></span>
                </div>
                <div class="relative flex-1 min-h-0 overflow-y-auto overflow-x-hidden no-scrollbar px-5 py-4">
                    <template x-if="announcements.length === 0">
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <span class="mb-3 grid h-16 w-16 place-items-center rounded-full bg-amber-100/70 text-amber-400 dark:bg-amber-500/10"><i data-lucide="megaphone" class="h-7 w-7"></i></span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">No announcements</p>
                            <p class="mt-1 max-w-[210px] text-xs text-slate-400">You're all caught up — nothing new right now.</p>
                        </div>
                    </template>
                    <div class="space-y-2.5">
                        <template x-for="a in announcements" :key="a.id">
                            <div class="flex items-center gap-3 rounded-lg p-1.5 -mx-1.5 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                                <div class="h-9 w-10 rounded-lg flex items-center justify-center bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400 shrink-0">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l14-5v13L3 14z"/><path stroke-linecap="round" stroke-linejoin="round" d="M11.6 16.8a3 3 0 11-5.8-1.6"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate">
                                        <span x-show="a.pinned">📌 </span><span x-text="a.title"></span>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate" x-text="a.expires_label ? ('Expires ' + a.expires_label) : 'No expiry'"></p>
                                </div>
                                <button type="button" @click="openView(a)" class="shrink-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">View</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Upcoming events -->
            <div class="relative overflow-hidden flex flex-col h-[300px] rounded-2xl border border-slate-200/70 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <div class="relative flex items-center gap-2.5 px-5 pt-5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400"><i data-lucide="calendar-days" class="h-5 w-5"></i></span>
                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Upcoming events</h3>
                    <span class="ml-auto grid h-6 min-w-6 place-items-center rounded-full bg-slate-100 px-1.5 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300" x-text="count('events')"></span>
                </div>
                <div class="relative flex-1 min-h-0 overflow-y-auto overflow-x-hidden no-scrollbar px-5 py-4">
                    <template x-if="upcomingEvents().length === 0">
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <span class="mb-3 grid h-16 w-16 place-items-center rounded-full bg-sky-100/70 text-sky-400 dark:bg-sky-500/10"><i data-lucide="calendar-days" class="h-7 w-7"></i></span>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">No upcoming events</p>
                            <p class="mt-1 max-w-[210px] text-xs text-slate-400">Nothing scheduled — enjoy the calm!</p>
                        </div>
                    </template>
                    <div class="space-y-2.5">
                        <template x-for="e in upcomingEvents()" :key="e.id">
                            <div class="flex items-start gap-3 rounded-lg p-1.5 -mx-1.5 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full shrink-0" :class="dotBg(e.color)"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="e.title"></p>
                                    <p class="text-xs text-slate-400 truncate">
                                        <span x-text="eventWhen(e)"></span><template x-if="e.location"><span x-text="' · ' + e.location"></span></template>
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <a href="{{ route('events.employee-calendar') }}" class="relative px-5 pb-4 text-sm font-bold text-teal-600 hover:text-teal-700 dark:text-teal-400">View all →</a>
            </div>

        </div>

        <!-- Out of office bar -->
        <button type="button" @click="oooOpen = true; $nextTick(() => window.lucide && lucide.createIcons())"
                class="group relative flex w-full items-center justify-between rounded-2xl border border-slate-200/70 bg-white px-5 py-4 text-left shadow-sm transition hover:border-slate-300 dark:bg-slate-800 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-100 text-blue-500 dark:bg-blue-500/15 dark:text-blue-400"><i data-lucide="users" class="h-5 w-5"></i></span>
                <div class="text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-bold text-slate-800 dark:text-white" x-text="oooOnDate().length"></span>
                    <span x-text="oooOnDate().length === 1 ? 'employee' : 'employees'"></span> out of office
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex -space-x-2">
                    <template x-for="(o, i) in oooOnDate().slice(0, 3)" :key="'av' + i">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full ring-2 ring-white dark:ring-slate-800 overflow-hidden bg-gradient-to-br from-brand-400 to-indigo-500 text-white text-[10px] font-bold">
                            <template x-if="o.avatar"><img :src="o.avatar" class="h-full w-full object-cover"></template>
                            <template x-if="!o.avatar"><span x-text="o.initials"></span></template>
                        </span>
                    </template>
                </div>
                <span class="grid h-8 w-8 place-items-center rounded-full bg-slate-50 text-slate-400 transition group-hover:bg-slate-900 group-hover:text-white dark:bg-slate-700/60 dark:group-hover:bg-white dark:group-hover:text-slate-900"><i data-lucide="chevron-right" class="h-4 w-4"></i></span>
            </div>
        </button>
        @include('dashboard.partials.ooo-modal')

                <!-- Announcement viewer popup -->
                <template x-teleport="body">
                    <div x-show="selected" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" x-transition.opacity>
                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="selected = null"></div>
                        <div class="relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/70 dark:border-slate-700 max-h-[85vh] overflow-y-auto"
                             @keydown.escape.window="selected = null">
                            <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                        <span x-show="selected && selected.pinned">📌</span><span x-text="selected ? selected.title : ''"></span>
                                    </h3>
                                    <p class="text-[11px] text-slate-400 mt-0.5">
                                        <span x-text="selected ? selected.author : ''"></span>
                                        <span x-text="selected ? (' · Posted ' + selected.posted_label) : ''"></span>
                                        <template x-if="selected && selected.expires_label"><span x-text="' · Expires ' + selected.expires_label"></span></template>
                                    </p>
                                </div>
                                <button type="button" @click="selected = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 shrink-0">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="px-6 py-5 text-sm text-slate-700 dark:text-slate-200 leading-relaxed break-words" x-html="selected ? selected.body_html : ''"></div>
                        </div>
                    </div>
                </template>

                <script>
                window.__celebrations = @json($celebrations);
                window.__events = @json($events);
                window.__holidays = @json($holidays);
                window.__announcements = @json($announcements ?? []);
                window.__outOfOffice = @json($outOfOffice ?? []);
                window.__workFromHome = @json($workFromHome ?? []);
                window.__remoteWorkers = @json($remoteWorkers ?? []);
                function celebrationsWidget() {
                    return {
                        current: '{{ now()->toDateString() }}',
                        tab: 'celebrations',
                        tabs: [{ key: 'celebrations', label: 'Celebrations' }, { key: 'announcements', label: 'Announcements' }],
                        celebrations: window.__celebrations || [],
                        events: window.__events || [],
                        announcements: window.__announcements || [],
                        holidays: window.__holidays || [],
                        outOfOffice: window.__outOfOffice || [],
                        workFromHome: window.__workFromHome || [],
                        remoteWorkers: window.__remoteWorkers || [],
                        selected: null,
                        openView(a) { this.selected = a; },
                        oooOpen: false,
                        oooSearch: '',
                        oooTab: 'leave',
                        oooOnDate() { return this.outOfOffice.filter(o => this.current >= o.start && this.current <= o.end); },
                        oooFiltered() { const q = this.oooSearch.toLowerCase(); return this.oooOnDate().filter(o => o.name.toLowerCase().includes(q)); },
                        wfhOnDate() {
                            const d = this.current;
                            const wd = new Date(d + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short' });
                            const seen = {};
                            const out = [];
                            this.workFromHome.forEach(o => { if (d >= o.start && d <= o.end) { seen[o.id] = 1; out.push(Object.assign({}, o, { detail: 'Working remotely' })); } });
                            (this.remoteWorkers || []).forEach(o => { if (seen[o.id]) return; if (o.everyday || (o.days || []).indexOf(wd) !== -1) { out.push(Object.assign({}, o, { detail: 'Working remotely' })); } });
                            return out;
                        },
                        wfhFiltered() { const q = this.oooSearch.toLowerCase(); return this.wfhOnDate().filter(o => o.name.toLowerCase().includes(q)); },
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
                            if (key === 'announcements') return this.announcements.length;
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

    {{-- Quick request cards (functionality wired later) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
            $quickCards = [
                ['title' => 'Request Time Off',      'text' => 'Apply for leave from your policies.',        'icon' => 'calendar-plus', 'tone' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400', 'hue' => 'indigo', 'href' => route('time-off.create')],
                ['title' => 'Request Login Code',     'text' => 'Get a one-time code for a company tool.',    'icon' => 'key-round',     'tone' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', 'hue' => 'amber', 'event' => 'open-code-request'],
                ['title' => 'Feedback & Suggestions', 'text' => 'Share feedback or raise an issue with HR.', 'icon' => 'message-square-heart', 'tone' => 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400', 'hue' => 'rose', 'event' => 'open-feedback'],
            ];
        @endphp
        @foreach($quickCards as $card)
            @include('dashboard.partials.action-card')
        @endforeach
    </div>

    {{-- Bottom action cards (functionality wired later) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @php
            $dashCards = [
                ['title' => 'Equipment',          'text' => 'Request approval to take a company item home.', 'icon' => 'package',      'tone' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'hue' => 'emerald', 'href' => route('equipment.index')],
                ['title' => 'Overtime Approval',   'text' => 'Submit overtime for approval.',               'icon' => 'alarm-clock',  'tone' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400', 'hue' => 'violet', 'href' => route('time-off.index', ['overtime' => 1])],
                ['title' => 'WFH Approval',        'text' => 'Request to work from home — approved per request.', 'icon' => 'house',   'tone' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400', 'hue' => 'sky', 'href' => route('time-off.create', ['policy' => 'wfh'])],
            ];
        @endphp
        @foreach($dashCards as $card)
            @include('dashboard.partials.action-card')
        @endforeach
    </div>

    </div>{{-- /space-y-6 --}}
    @include('partials.code-request-modal')
    @include('partials.feedback-modal')
</div>
@endsection
