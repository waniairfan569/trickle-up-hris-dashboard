@extends('layouts.hr-app')

@section('title', 'Team Attendance History')
@section('breadcrumb', 'Attendance > Team History')

@section('content')
<div class="space-y-6">

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
                                {{ $record->clock_in ? $record->clock_in->format('h:i A') : '--:--' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono text-slate-700">
                                {{ $record->clock_out ? $record->clock_out->format('h:i A') : '--:--' }}
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">
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
