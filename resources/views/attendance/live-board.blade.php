@extends('layouts.hr-app')

@section('title', 'Today\'s Attendance')
@section('breadcrumb', 'Attendance > Live Board')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between gap-2 flex-wrap">
        <h2 class="text-2xl font-bold text-slate-800">Today: {{ now()->format('l, F j, Y') }}</h2>
        <div class="flex items-center gap-2">
            @if(auth()->user()->isAdmin())
            <a href="{{ route('attendance.backfill') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 bg-brand-50 hover:bg-brand-100 px-3 py-1.5 rounded-lg transition"><i data-lucide="calendar-plus" class="w-4 h-4"></i> Add attendance</a>
            @php $lateCut = \Carbon\Carbon::parse(\App\Models\AttendanceRecord::lateCutoff())->format('g:i A'); @endphp
            <form method="POST" action="{{ route('attendance.recalc-late') }}" onsubmit="return confirm('Re-check today\'s attendance against the late rule? Anyone who clocked in at or after {{ $lateCut }} will be marked Late.');">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition"><i data-lucide="alarm-clock" class="w-4 h-4"></i> Fix late status</button>
            </form>
            @endif
            <a href="{{ route('attendance.on-leave') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition"><i data-lucide="palmtree" class="w-4 h-4"></i> On Leave</a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-3 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif

    <!-- On leave today -->
    @include('partials.on-leave-today', ['people' => $onLeavePeople, 'compact' => true])

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Clocked In</p>
                <p class="text-3xl font-black text-brand-600">{{ $summary['clocked_in'] }}</p>
            </div>
            <div class="w-12 h-12 bg-brand-50 text-brand-600 rounded-full flex items-center justify-center">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Late</p>
                <p class="text-3xl font-black text-amber-500">{{ $summary['late'] }}</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center">
                <i data-lucide="clock" class="w-6 h-6"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Absent</p>
                <p class="text-3xl font-black text-red-500">{{ $summary['absent'] }}</p>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
                <i data-lucide="user-x" class="w-6 h-6"></i>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">On Leave</p>
                <p class="text-3xl font-black text-blue-500">{{ $summary['on_leave'] }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center">
                <i data-lucide="calendar" class="w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Live Board -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="font-bold text-slate-800">Live Status</h3>
            <form method="GET" class="flex space-x-3">
                <select name="department_id" class="text-sm border-slate-300 rounded-lg focus:ring-brand-500 focus:border-brand-500 shadow-sm py-1.5" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="text-sm border-slate-300 rounded-lg focus:ring-brand-500 focus:border-brand-500 shadow-sm py-1.5" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                    <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                    <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                    <option value="on_leave" {{ request('status') == 'on_leave' ? 'selected' : '' }}>On Leave</option>
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-white text-slate-500 font-medium uppercase text-xs border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Clock In</th>
                        <th class="px-6 py-4">Clock Out</th>
                        <th class="px-6 py-4">Hours</th>
                        <th class="px-6 py-4">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="live-board-body">
                    @php $tzService = app(\App\Services\TimezoneService::class); @endphp
                    @forelse($records as $record)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold text-sm">
                                            {{ Str::substr($record->employee->first_name, 0, 1) }}{{ Str::substr($record->employee->last_name, 0, 1) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-bold text-slate-800">{{ $record->employee->first_name }} {{ $record->employee->last_name }}</div>
                                        <div class="text-xs text-slate-500">{{ $record->employee->job_title ?? 'Employee' }} &bull; {{ optional($record->employee->department)->name ?? 'No Dept' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700">
                                @if($record->clock_in)
                                    {{ $tzService->formatForUser($record->clock_in, $record->employee, 'h:i A') }}
                                    <span class="ml-1 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500 align-middle">{{ $tzService->abbreviation($record->employee) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700">
                                {{ $record->clock_out ? $tzService->formatForUser($record->clock_out, $record->employee, 'h:i A') : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800">
                                {{ $record->hours_worked ?? '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $record->status_color }}">
                                    {{ str_replace('_', ' ', Str::title($record->status)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                <i data-lucide="users" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
                                <p>No records found for today.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    // Refresh page every 60 seconds to act as a live board
    setTimeout(function() {
        window.location.reload();
    }, 60000);
</script>
@endsection
