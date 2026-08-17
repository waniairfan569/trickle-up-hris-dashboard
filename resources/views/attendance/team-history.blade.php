@extends('layouts.hr-app')

@section('title', 'Team Attendance History')
@section('breadcrumb', 'Team History')

@section('content')
@php
    $canEdit = auth()->user()->isAdmin();
    $view = $view ?? 'list';

    // Preserve the active filters when switching view / month.
    $baseFilters = request()->only(['employee_id', 'status', 'department_id']);

    // Quick date ranges for the list view.
    $rt = now();
    $quick = [
        'This week'  => [$rt->copy()->startOfWeek()->toDateString(),  $rt->copy()->endOfWeek()->toDateString()],
        'This month' => [$rt->copy()->startOfMonth()->toDateString(), $rt->copy()->endOfMonth()->toDateString()],
        'Last month' => [$rt->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $rt->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'This year'  => [$rt->copy()->startOfYear()->toDateString(),  $rt->copy()->endOfYear()->toDateString()],
    ];

    // status → cell colour + label for the calendar matrix.
    $calMeta = fn ($s) => match ($s) {
        'present'           => ['bg' => 'bg-emerald-400', 'label' => 'Present'],
        'late'              => ['bg' => 'bg-amber-400',   'label' => 'Late'],
        'early_departure'   => ['bg' => 'bg-orange-400',  'label' => 'Early departure'],
        'overtime'          => ['bg' => 'bg-purple-400',  'label' => 'Overtime'],
        'half_day'          => ['bg' => 'bg-sky-400',     'label' => 'Half day'],
        'absent'            => ['bg' => 'bg-rose-400',    'label' => 'Absent'],
        'on_leave'          => ['bg' => 'bg-indigo-400',  'label' => 'On leave'],
        'public_holiday'    => ['bg' => 'bg-violet-400',  'label' => 'Holiday'],
        'missing_clock_out' => ['bg' => 'bg-orange-300',  'label' => 'No clock-out'],
        default             => ['bg' => 'bg-slate-100 dark:bg-slate-700/50', 'label' => '—'],
    };
@endphp
<style>[x-cloak]{display:none!important}</style>
<div class="space-y-6">

    <!-- On leave today -->
    @include('partials.on-leave-today', ['people' => $onLeavePeople, 'compact' => true])

    <!-- View toggle -->
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="inline-flex rounded-xl border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-slate-900/40">
            <a href="{{ route('attendance.team', array_merge($baseFilters, ['view' => 'list'])) }}"
               class="{{ $view === 'list' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400' }} inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-lg transition">
                <i data-lucide="list" class="h-4 w-4"></i> List
            </a>
            <a href="{{ route('attendance.team', array_merge($baseFilters, ['view' => 'calendar'])) }}"
               class="{{ $view === 'calendar' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400' }} inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-lg transition">
                <i data-lucide="calendar-days" class="h-4 w-4"></i> Calendar
            </a>
        </div>
    </div>

    {{-- ══════════════════════ LIST VIEW ══════════════════════ --}}
    @if($view === 'list')
        <!-- Filters -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                    <input type="date" id="th-from" name="date_from" value="{{ request('date_from') }}" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                    <input type="date" id="th-to" name="date_to" value="{{ request('date_to') }}" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Employee</label>
                    <select id="th-employee" name="employee_id" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="">All Employees</option>
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" {{ request('employee_id') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                    <select name="status" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="">All Statuses</option>
                        <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                        <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                        <option value="early_departure" {{ request('status') == 'early_departure' ? 'selected' : '' }}>Early Departure</option>
                        <option value="half_day" {{ request('status') == 'half_day' ? 'selected' : '' }}>Half Day</option>
                        <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                        <option value="overtime" {{ request('status') == 'overtime' ? 'selected' : '' }}>Overtime</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full btn-dark">Filter</button>
                </div>
            </form>
            <div class="flex flex-wrap items-center gap-1.5 mt-3">
                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mr-1">Quick:</span>
                @foreach($quick as $label => [$qf, $qt])
                    @php $active = request('date_from') === $qf && request('date_to') === $qt; @endphp
                    <a href="{{ route('attendance.team', array_merge($baseFilters, ['date_from' => $qf, 'date_to' => $qt])) }}"
                       class="rounded-full px-3 py-1 text-[11px] font-bold {{ $active ? 'bg-brand-500 text-slate-900' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300' }}">{{ $label }}</a>
                @endforeach
                @if(request('date_from') || request('date_to'))
                    <a href="{{ route('attendance.team', $baseFilters) }}" class="rounded-full px-3 py-1 text-[11px] font-bold text-slate-400 hover:text-slate-600">Clear</a>
                @endif
            </div>
        </div>

        <!-- Details Table -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 dark:text-white">History Records</h3>
                <div class="flex items-center gap-2">
                    @if(auth()->user()->isAdmin())
                        {{-- Uses the CURRENT From/To date inputs (not the last submitted filter),
                             so picking a single day fixes only that day. --}}
                        <form method="POST" action="{{ route('attendance.recalc-late') }}" onsubmit="return prepFixLate(this)">
                            @csrf
                            <input type="hidden" name="date_from">
                            <input type="hidden" name="date_to">
                            <input type="hidden" name="employee_id">
                            <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                            <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition dark:bg-amber-500/10 dark:text-amber-400"><i data-lucide="alarm-clock" class="w-4 h-4"></i> Fix late status</button>
                        </form>
                        <script>
                            function prepFixLate(form) {
                                var fromEl = document.getElementById('th-from');
                                var toEl = document.getElementById('th-to');
                                var empEl = document.getElementById('th-employee');
                                var from = fromEl && fromEl.value ? fromEl.value : '';
                                var to = toEl && toEl.value ? toEl.value : '';
                                if (!from && !to) {
                                    // No dates chosen → default to the current month.
                                    var n = new Date(), y = n.getFullYear(), m = n.getMonth();
                                    var pad = function (x) { return String(x).padStart(2, '0'); };
                                    from = y + '-' + pad(m + 1) + '-01';
                                    to = y + '-' + pad(m + 1) + '-' + pad(new Date(y, m + 1, 0).getDate());
                                } else {
                                    if (!from) from = to;   // only one bound set → single day
                                    if (!to) to = from;
                                }
                                form.date_from.value = from;
                                form.date_to.value = to;
                                form.employee_id.value = empEl ? empEl.value : '';

                                var M = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                                var lbl = function (d) { var p = d.split('-'); return parseInt(p[2], 10) + ' ' + M[parseInt(p[1], 10) - 1] + ' ' + p[0]; };
                                var range = (from === to) ? lbl(from) : (lbl(from) + ' to ' + lbl(to));
                                return confirm('Re-check every clock-in for ' + range + " against each employee's shift late rule? Statuses are corrected and reflected in their attendance.");
                            }
                        </script>
                    @endif
                    <a href="{{ route('attendance.team.export', request()->query()) }}" class="flex items-center text-sm font-semibold text-brand-600 bg-brand-50 hover:bg-brand-100 px-3 py-1.5 rounded-lg transition dark:bg-brand-500/10 dark:text-brand-400"><i data-lucide="download" class="w-4 h-4 mr-1.5"></i> Export CSV</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 font-medium uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Employee</th><th class="px-6 py-3">Date</th><th class="px-6 py-3">Clock In</th><th class="px-6 py-3">Clock Out</th><th class="px-6 py-3">Hours</th><th class="px-6 py-3">Late (min)</th><th class="px-6 py-3">OT (min)</th><th class="px-6 py-3">Status</th>@if($canEdit)<th class="px-6 py-3 text-right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($records as $record)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                                <td class="px-6 py-4 whitespace-nowrap"><span class="font-bold text-slate-800 dark:text-white">{{ $record->employee->first_name }} {{ $record->employee->last_name }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap"><span class="font-medium text-slate-700 dark:text-slate-300">{{ $record->date->format('M d, Y') }}</span></td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">{{ $record->clock_in_local ?? '--:--' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">{{ $record->clock_out_local ?? '--:--' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800 dark:text-white">{{ $record->hours_worked ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-amber-600 font-medium">{{ $record->late_minutes > 0 ? $record->late_minutes : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-purple-600 font-medium">{{ $record->overtime_minutes > 0 ? $record->overtime_minutes : '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap"><span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $record->status_color }}">{{ str_replace('_', ' ', Str::title($record->status)) }}</span></td>
                                @if($canEdit)
                                @php
                                    $tzSvc = app(\App\Services\TimezoneService::class);
                                    $ciVal = $record->clock_in ? $tzSvc->toUserTime($record->clock_in, $record->employee)->format('H:i') : '';
                                    $coVal = $record->clock_out ? $tzSvc->toUserTime($record->clock_out, $record->employee)->format('H:i') : '';
                                @endphp
                                <td class="px-6 py-4 whitespace-nowrap text-right" x-data="{ open: false }">
                                    <button type="button" @click="open = true" class="inline-flex items-center text-sm font-semibold text-brand-600 hover:text-brand-800"><i data-lucide="pencil" class="w-4 h-4 mr-1"></i> Edit</button>
                                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
                                        <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
                                        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6 text-left">
                                            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">Edit attendance time</h3>
                                            <p class="text-sm text-slate-500 mb-4">{{ $record->employee->first_name }} {{ $record->employee->last_name }} · <b>{{ $record->date->format('M d, Y') }}</b></p>
                                            <form method="POST" action="{{ route('attendance.records.update-times', $record) }}" class="space-y-4">
                                                @csrf @method('PUT')
                                                <div><label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Clock In</label><input type="time" name="clock_in" value="{{ $ciVal }}" class="w-full text-sm rounded-lg border-slate-300 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                                <div><label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Clock Out</label><input type="time" name="clock_out" value="{{ $coVal }}" class="w-full text-sm rounded-lg border-slate-300 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                                <p class="text-xs text-slate-400">Times are in {{ $record->employee->first_name }}'s timezone. Clock-in at or after {{ \App\Models\AttendanceRecord::lateCutoffLabel() }} is marked late.</p>
                                                <div class="flex justify-end gap-2 pt-2"><button type="button" @click="open = false" class="btn-outline btn-sm">Cancel</button><button type="submit" class="btn-brand btn-sm">Save</button></div>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canEdit ? 9 : 8 }}" class="px-6 py-12 text-center text-slate-500"><i data-lucide="file-search" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i><p>No records match your filters.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($records->hasPages())
                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">{{ $records->withQueryString()->links() }}</div>
            @endif
        </div>
    @else
        {{-- ══════════════════════ CALENDAR (MONTH MATRIX) ══════════════════════ --}}
        @php
            $prevMonth = $calMonth->copy()->subMonthNoOverflow()->format('Y-m');
            $nextMonth = $calMonth->copy()->addMonthNoOverflow()->format('Y-m');
            $navBase = array_merge($baseFilters, ['view' => 'calendar']);
            $todayStr = now()->toDateString();
        @endphp

        <!-- Filters (employee / status / department) -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="view" value="calendar">
                <input type="hidden" name="month" value="{{ $calMonth->format('Y-m') }}">
                <div class="min-w-[180px]">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">Employee</label>
                    <select name="employee_id" class="w-full text-sm rounded-lg border-slate-300 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="">All Employees</option>
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" {{ request('employee_id') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">Department</label>
                    <select name="department_id" class="w-full text-sm rounded-lg border-slate-300 shadow-sm py-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-dark btn-sm">Apply</button>
            </form>
        </div>

        <!-- Month matrix -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <div class="flex items-center gap-2">
                    <a href="{{ route('attendance.team', array_merge($navBase, ['month' => $prevMonth])) }}" class="h-8 w-8 grid place-items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
                    <h3 class="font-bold text-slate-800 dark:text-white min-w-[130px] text-center">{{ $calMonth->format('F Y') }}</h3>
                    <a href="{{ route('attendance.team', array_merge($navBase, ['month' => $nextMonth])) }}" class="h-8 w-8 grid place-items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
                    <a href="{{ route('attendance.team', array_merge($navBase, ['month' => now()->format('Y-m')])) }}" class="ml-1 text-[11px] font-bold text-brand-600 hover:text-brand-700">This month</a>
                </div>
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-bold text-slate-500 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <i data-lucide="calendar" class="h-3.5 w-3.5"></i> Pick month
                        <input type="month" value="{{ $calMonth->format('Y-m') }}" class="absolute inset-0 h-full w-full opacity-0 cursor-pointer"
                               onchange="if(this.value){window.location='{{ route('attendance.team', $navBase) }}&month='+this.value}">
                    </label>
                    <a href="{{ route('attendance.team.export', array_merge($baseFilters, ['date_from' => $calMonth->copy()->startOfMonth()->toDateString(), 'date_to' => $calMonth->copy()->endOfMonth()->toDateString()])) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 bg-brand-50 hover:bg-brand-100 px-3 py-1.5 rounded-lg dark:bg-brand-500/10 dark:text-brand-400"><i data-lucide="download" class="h-4 w-4"></i> Export</a>
                </div>
            </div>

            @if($calEmployees->isEmpty())
                <div class="py-16 text-center text-slate-500"><i data-lucide="users" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i><p>No employees to show.</p></div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full border-collapse">
                        <thead>
                            <tr class="text-slate-400">
                                <th class="sticky left-0 z-10 bg-white dark:bg-slate-800 text-left text-[11px] font-bold uppercase tracking-wide px-4 py-2 border-b border-slate-100 dark:border-slate-700/60">Employee</th>
                                @foreach($calDays as $day)
                                    @php $wk = $day->isWeekend(); $isToday = $day->toDateString() === $todayStr; @endphp
                                    <th class="px-1 py-1.5 text-center border-b border-slate-100 dark:border-slate-700/60 {{ $wk ? 'bg-slate-50/60 dark:bg-slate-900/30' : '' }}">
                                        <div class="text-[8px] font-bold {{ $wk ? 'text-slate-300' : 'text-slate-400' }}">{{ substr($day->format('D'), 0, 1) }}</div>
                                        <div class="text-[11px] font-bold grid place-items-center h-5 w-5 mx-auto rounded-full {{ $isToday ? 'bg-brand-500 text-slate-900' : 'text-slate-500 dark:text-slate-400' }}">{{ $day->format('j') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($calEmployees as $emp)
                                <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20">
                                    <td class="sticky left-0 z-10 bg-white dark:bg-slate-800 px-4 py-1.5 border-b border-slate-50 dark:border-slate-700/40 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @if(!empty($emp->avatar_url))
                                                <img src="{{ $emp->avatar_url }}" alt="" class="h-6 w-6 rounded-full object-cover bg-slate-100 shrink-0">
                                            @else
                                                <span class="h-6 w-6 shrink-0 grid place-items-center rounded-full bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 text-[9px] font-bold text-slate-600 dark:text-slate-200">{{ strtoupper(substr($emp->first_name,0,1).substr($emp->last_name,0,1)) }}</span>
                                            @endif
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 max-w-[140px] truncate">{{ $emp->first_name }} {{ $emp->last_name }}</span>
                                        </div>
                                    </td>
                                    @foreach($calDays as $day)
                                        @php
                                            $ds = $day->toDateString();
                                            $st = $calMatrix[$emp->id][$ds] ?? null;
                                            $m = $calMeta($st);
                                            $wk = $day->isWeekend();
                                        @endphp
                                        <td class="p-0.5 text-center border-b border-slate-50 dark:border-slate-700/40 {{ $wk ? 'bg-slate-50/40 dark:bg-slate-900/20' : '' }}">
                                            <span class="inline-block h-5 w-5 rounded {{ $m['bg'] }}" title="{{ $emp->first_name }} {{ $emp->last_name }} · {{ $day->format('D, d M') }} · {{ $m['label'] }}"></span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 border-t border-slate-100 dark:border-slate-700/60">
                    @foreach(['present','late','early_departure','overtime','half_day','absent','on_leave','public_holiday'] as $s)
                        @php $m = $calMeta($s); @endphp
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400"><span class="h-3 w-3 rounded {{ $m['bg'] }}"></span>{{ $m['label'] }}</span>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-400"><span class="h-3 w-3 rounded bg-slate-100 dark:bg-slate-700/50"></span>No record</span>
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
