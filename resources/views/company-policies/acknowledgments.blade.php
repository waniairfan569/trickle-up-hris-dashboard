@extends('layouts.hr-app')

@section('title', 'Tracking — ' . $policy->title)
@section('breadcrumb', 'Company Policies')

@php
    $ackBadge = ['pending' => 'bg-amber-50 text-amber-700', 'viewed' => 'bg-sky-50 text-sky-700', 'acknowledged' => 'bg-emerald-50 text-emerald-700'];
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <a href="{{ route('company-policies.show', $policy) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to policy</a>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Acknowledgment tracking</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $policy->title }} · v{{ $policy->version }}</p>
        </div>
        <a href="{{ route('company-policies.export', $policy) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="download" class="h-4 w-4"></i> Export</a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach([['Assigned', $stats['assigned'], 'users', 'text-slate-600'], ['Acknowledged', $stats['acknowledged'], 'check-circle-2', 'text-emerald-600'], ['Pending', $stats['pending'], 'clock', 'text-amber-600'], ['Overdue', $stats['overdue'], 'alert-triangle', 'text-rose-600']] as [$label, $val, $icon, $color])
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-2 {{ $color }}"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i><span class="text-xs font-bold uppercase tracking-wider">{{ $label }}</span></div>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/40 text-xs font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-5 py-3">Employee</th>
                        <th class="text-left px-5 py-3">Department</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-left px-5 py-3">Acknowledged</th>
                        <th class="text-left px-5 py-3">Signature</th>
                        <th class="text-right px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($acks as $ack)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-slate-800 dark:text-white">{{ optional($ack->employee)->full_name ?? 'Unknown' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ optional(optional($ack->employee)->department)->name ?? '—' }}</td>
                            <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $ackBadge[$ack->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($ack->status) }}</span></td>
                            <td class="px-5 py-3 text-slate-500">{{ optional($ack->acknowledged_at)->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">@if($ack->signature_type){{ ucfirst($ack->signature_type) }}@if($ack->signature_name) <span class="text-slate-400">({{ $ack->signature_name }})</span>@endif @else — @endif</td>
                            <td class="px-5 py-3 text-right">
                                @if($ack->status !== 'acknowledged')
                                    <button onclick="sendReminder({{ $ack->id }}, this)" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="bell" class="h-3.5 w-3.5"></i> Remind</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">No one has been assigned this policy yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function sendReminder(id, btn) {
        btn.disabled = true; btn.style.opacity = .5;
        fetch(`/policy-acknowledgments/${id}/remind`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
        }).then(r => r.json()).then(d => {
            btn.innerHTML = d.sent ? '<i data-lucide="check" class="h-3.5 w-3.5"></i> Sent' : 'Failed';
            if (window.lucide) lucide.createIcons();
        }).catch(() => { btn.innerHTML = 'Failed'; btn.disabled = false; btn.style.opacity = 1; });
    }
</script>
@endsection
