@extends('layouts.hr-app')

@section('title', 'Pending Corrections')
@section('breadcrumb', 'Attendance > Corrections')

@section('content')
<div class="space-y-6">

    @if(session('success'))
        <div class="p-4 bg-green-50 text-green-700 rounded-xl border border-green-200 font-medium flex items-center shadow-sm">
            <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-slate-800">Pending Requests</h3>
            <span class="bg-brand-100 text-brand-700 py-1 px-3 rounded-full text-xs font-bold">{{ $corrections->total() }} Pending</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-white text-slate-500 font-medium uppercase text-xs border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4 text-center">Requested Time</th>
                        <th class="px-6 py-4">Reason</th>
                        <th class="px-6 py-4">Submitted At</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($corrections as $c)
                        <tr class="hover:bg-slate-50 transition group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800">{{ $c->employee->first_name }} {{ $c->employee->last_name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-slate-700">{{ $c->correction_date->format('M d, Y') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @php $tzSvc = app(\App\Services\TimezoneService::class); @endphp
                                <div class="inline-flex items-center space-x-2 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                                    <span class="font-mono text-brand-700 font-semibold">{{ $c->requested_clock_in ? $tzSvc->formatForUser($c->requested_clock_in, $c->employee, 'h:i A') : '--:--' }}</span>
                                    <i data-lucide="arrow-right" class="w-4 h-4 text-slate-400"></i>
                                    <span class="font-mono text-brand-700 font-semibold">{{ $c->requested_clock_out ? $tzSvc->formatForUser($c->requested_clock_out, $c->employee, 'h:i A') : '--:--' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-slate-600 max-w-xs truncate" title="{{ $c->reason }}">{{ $c->reason }}</p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                {{ $c->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                                <!-- Reject Button -->
                                <button onclick="openRejectModal({{ $c->id }})" class="text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg transition">
                                    Reject
                                </button>
                                <!-- Approve Form -->
                                <form action="{{ route('attendance.corrections.approve', $c) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="text-sm font-semibold text-white bg-green-600 hover:bg-green-700 px-3 py-1.5 rounded-lg shadow-sm transition">
                                        Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-slate-500">
                                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                                    <i data-lucide="check-circle-2" class="w-8 h-8"></i>
                                </div>
                                <h4 class="text-lg font-bold text-slate-700 mb-1">All caught up!</h4>
                                <p>There are no pending attendance corrections to review.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($corrections->hasPages())
            <div class="px-6 py-4 border-t border-slate-200">
                {{ $corrections->links() }}
            </div>
        @endif
    </div>

</div>

<!-- Reject Modal -->
<div id="reject-modal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-200" id="reject-modal-content">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800 flex items-center">
                <i data-lucide="x-circle" class="w-5 h-5 text-red-500 mr-2"></i> Reject Correction
            </h3>
            <button onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form id="reject-form" method="POST">
            @csrf
            <div class="p-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Reason for Rejection <span class="text-red-500">*</span></label>
                <textarea name="reviewer_note" required rows="3" placeholder="Explain why this request is being rejected..." class="w-full rounded-lg border-slate-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"></textarea>
            </div>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end space-x-3">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm transition">Reject Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal(id) {
        document.getElementById('reject-form').action = `/attendance/corrections/${id}/reject`;
        const modal = document.getElementById('reject-modal');
        const content = document.getElementById('reject-modal-content');
        modal.classList.remove('hidden');
        setTimeout(() => content.classList.remove('scale-95'), 10);
    }

    function closeRejectModal() {
        const modal = document.getElementById('reject-modal');
        const content = document.getElementById('reject-modal-content');
        content.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 200);
    }
</script>
@endsection
