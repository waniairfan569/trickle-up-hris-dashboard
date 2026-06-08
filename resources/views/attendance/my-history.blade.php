@extends('layouts.hr-app')

@section('title', 'My Attendance History')
@section('breadcrumb', 'Attendance > My History')

@section('content')
<div class="space-y-6">

    <!-- Month Navigation -->
    <div class="flex items-center justify-between bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <a href="{{ route('attendance.my-history', ['month' => $date->copy()->subMonth()->month, 'year' => $date->copy()->subMonth()->year]) }}" class="p-2 hover:bg-slate-100 rounded-lg text-slate-500">
            <i data-lucide="chevron-left" class="w-5 h-5"></i>
        </a>
        <h2 class="text-xl font-bold text-slate-800">{{ $date->format('F Y') }}</h2>
        <a href="{{ route('attendance.my-history', ['month' => $date->copy()->addMonth()->month, 'year' => $date->copy()->addMonth()->year]) }}" class="p-2 hover:bg-slate-100 rounded-lg text-slate-500">
            <i data-lucide="chevron-right" class="w-5 h-5"></i>
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-center items-center">
            <span class="text-3xl font-black text-green-600 mb-1">{{ $summary['present'] }}</span>
            <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Days Present</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-center items-center">
            <span class="text-3xl font-black text-amber-500 mb-1">{{ $summary['late'] }}</span>
            <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Days Late</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-center items-center">
            <span class="text-3xl font-black text-red-500 mb-1">{{ $summary['absent'] }}</span>
            <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Days Absent</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-center items-center">
            <span class="text-3xl font-black text-brand-600 mb-1">{{ $summary['total_hours'] }}<span class="text-lg">h</span></span>
            <span class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Total Hours</span>
        </div>
    </div>

    <!-- Details Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="font-bold text-slate-800">Attendance Details</h3>
        </div>
        
        @if(session('success'))
            <div class="p-4 bg-green-50 text-green-700 border-b border-green-100 font-medium text-sm flex items-center">
                <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-slate-500 font-medium uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Clock In</th>
                        <th class="px-6 py-3">Clock Out</th>
                        <th class="px-6 py-3">Hours Worked</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($records as $record)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-slate-800">{{ $record->date->format('M d, Y') }}</span>
                                <div class="text-xs text-slate-400">{{ $record->date->format('l') }}</div>
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
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $record->status_color }}">
                                    {{ str_replace('_', ' ', Str::title($record->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if(in_array($record->status, ['absent', 'missing_clock_out', 'late', 'early_departure']))
                                    <button onclick="openCorrectionModal('{{ $record->date->format('Y-m-d') }}', '{{ $record->clock_in ? $record->clock_in->format('H:i') : '' }}', '{{ $record->clock_out ? $record->clock_out->format('H:i') : '' }}')" class="text-brand-600 hover:text-brand-800 font-medium text-sm transition">
                                        Submit Correction
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <i data-lucide="calendar-x" class="w-12 h-12 mx-auto text-slate-300 mb-3"></i>
                                <p>No attendance records found for this month.</p>
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

<!-- Correction Modal -->
<div id="correction-modal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Submit Attendance Correction</h3>
            <button onclick="closeCorrectionModal()" class="text-slate-400 hover:text-slate-600 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="{{ route('attendance.correction.submit') }}" method="POST">
            @csrf
            <div class="p-6 space-y-4">
                <input type="hidden" name="correction_date" id="correction_date">
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Date</label>
                    <div id="correction_date_display" class="font-medium text-slate-800 bg-slate-50 p-2 rounded-lg border border-slate-200"></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Requested Clock In</label>
                        <input type="time" name="requested_clock_in" id="requested_clock_in" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Requested Clock Out</label>
                        <input type="time" name="requested_clock_out" id="requested_clock_out" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Reason for Correction <span class="text-red-500">*</span></label>
                    <textarea name="reason" required rows="3" placeholder="Forgot to clock out, system error, etc." class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
                <button type="button" onclick="closeCorrectionModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-slate-900 bg-brand-600 rounded-lg hover:bg-brand-700 transition">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCorrectionModal(date, clockIn, clockOut) {
        document.getElementById('correction_date').value = date;
        document.getElementById('correction_date_display').innerText = date;
        document.getElementById('requested_clock_in').value = clockIn;
        document.getElementById('requested_clock_out').value = clockOut;
        document.getElementById('correction-modal').classList.remove('hidden');
    }

    function closeCorrectionModal() {
        document.getElementById('correction-modal').classList.add('hidden');
    }
</script>
@endsection
