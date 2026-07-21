@extends('layouts.hr-app')

@section('title', 'Team Attendance History')
@section('breadcrumb', 'Attendance > Team History')

@section('content')
@php $canEdit = auth()->user()->isAdmin(); @endphp
<style>[x-cloak]{display:none!important}</style>
<div class="space-y-6">

    <!-- On leave today -->
    @include('partials.on-leave-today', ['people' => $onLeavePeople, 'compact' => true])

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Employee</label>
                <select name="employee_id" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2">
                    <option value="">All Employees</option>
                    @foreach($teamMembers as $member)
                        <option value="{{ $member->id }}" {{ request('employee_id') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</label>
                <select name="status" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2">
                    <option value="">All Statuses</option>
                    <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                    <option value="late" {{ request('status') == 'late' ? 'selected' : '' }}>Late</option>
                    <option value="absent" {{ request('status') == 'absent' ? 'selected' : '' }}>Absent</option>
                    <option value="overtime" {{ request('status') == 'overtime' ? 'selected' : '' }}>Overtime</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Details Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="font-bold text-slate-800">History Records</h3>
            @if(auth()->user()->isAdmin() || auth()->user()->isManager())
            <a href="{{ route('attendance.team.export', request()->query()) }}" class="flex items-center text-sm font-semibold text-brand-600 bg-brand-50 hover:bg-brand-100 px-3 py-1.5 rounded-lg transition">
                <i data-lucide="download" class="w-4 h-4 mr-1.5"></i> Export CSV
            </a>
            @endif
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 font-medium uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Employee</th>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Clock In</th>
                        <th class="px-6 py-3">Clock Out</th>
                        <th class="px-6 py-3">Hours</th>
                        <th class="px-6 py-3">Late (min)</th>
                        <th class="px-6 py-3">OT (min)</th>
                        <th class="px-6 py-3">Status</th>
                        @if($canEdit)<th class="px-6 py-3 text-right">Actions</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($records as $record)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-slate-800">{{ $record->employee->first_name }} {{ $record->employee->last_name }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-slate-700">{{ $record->date->format('M d, Y') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700">
                                {{ $record->clock_in_local ?? '--:--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700">
                                {{ $record->clock_out_local ?? '--:--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800">
                                {{ $record->hours_worked ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-amber-600 font-medium">
                                {{ $record->late_minutes > 0 ? $record->late_minutes : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-purple-600 font-medium">
                                {{ $record->overtime_minutes > 0 ? $record->overtime_minutes : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $record->status_color }}">
                                    {{ str_replace('_', ' ', Str::title($record->status)) }}
                                </span>
                            </td>
                            @if($canEdit)
                            @php
                                $tzSvc = app(\App\Services\TimezoneService::class);
                                $ciVal = $record->clock_in ? $tzSvc->toUserTime($record->clock_in, $record->employee)->format('H:i') : '';
                                $coVal = $record->clock_out ? $tzSvc->toUserTime($record->clock_out, $record->employee)->format('H:i') : '';
                            @endphp
                            <td class="px-6 py-4 whitespace-nowrap text-right" x-data="{ open: false }">
                                <button type="button" @click="open = true" class="inline-flex items-center text-sm font-semibold text-brand-600 hover:text-brand-800">
                                    <i data-lucide="pencil" class="w-4 h-4 mr-1"></i> Edit
                                </button>

                                <!-- Edit times modal -->
                                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none">
                                    <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
                                    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md p-6 text-left">
                                        <h3 class="text-lg font-bold text-slate-800 mb-1">Edit attendance time</h3>
                                        <p class="text-sm text-slate-500 mb-4">{{ $record->employee->first_name }} {{ $record->employee->last_name }} · <b>{{ $record->date->format('M d, Y') }}</b></p>
                                        <form method="POST" action="{{ route('attendance.records.update-times', $record) }}" class="space-y-4">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Clock In</label>
                                                <input type="time" name="clock_in" value="{{ $ciVal }}" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Clock Out</label>
                                                <input type="time" name="clock_out" value="{{ $coVal }}" class="w-full text-sm rounded-lg border-slate-300 focus:ring-brand-500 focus:border-brand-500 shadow-sm py-2">
                                            </div>
                                            <p class="text-xs text-slate-400">Times are in {{ $record->employee->first_name }}'s timezone. Clock-in at or after {{ \App\Models\AttendanceRecord::lateCutoffLabel() }} is marked late.</p>
                                            <div class="flex justify-end gap-2 pt-2">
                                                <button type="button" @click="open = false" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg">Cancel</button>
                                                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEdit ? 9 : 8 }}" class="px-6 py-12 text-center text-slate-500">
                                <i data-lucide="file-search" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
                                <p>No records match your filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($records->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $records->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
