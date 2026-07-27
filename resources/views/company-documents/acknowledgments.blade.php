@extends('layouts.hr-app')

@section('title', 'Acknowledgments · ' . $document->title)
@section('breadcrumb', 'Company Documents')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <a href="{{ route('company-documents.admin') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All documents</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white mt-1">{{ $document->title }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Who has acknowledged this document.</p>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @php $pending = max(0, $total - $ackedCount); $pct = $total ? round($ackedCount / $total * 100) : 0; @endphp
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Acknowledged</p>
            <p class="text-3xl font-extrabold text-emerald-600 mt-1">{{ $ackedCount }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pending</p>
            <p class="text-3xl font-extrabold text-amber-600 mt-1">{{ $pending }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Completion</p>
            <p class="text-3xl font-extrabold text-slate-800 dark:text-white mt-1">{{ $pct }}%</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                    <th class="px-5 py-3">Employee</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Acknowledged on</th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr class="border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <td class="px-5 py-2.5 font-semibold text-slate-800 dark:text-white">{{ $r['user']->full_name }}</td>
                            <td class="px-5 py-2.5 text-slate-500">{{ optional($r['user']->department)->name ?? '—' }}</td>
                            <td class="px-5 py-2.5">
                                @if($r['ack'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"><i data-lucide="check" class="h-3 w-3"></i> Acknowledged</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Pending</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $r['ack'] ? $r['ack']->acknowledged_at->format('d M Y, H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-slate-400">No eligible employees for this document.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
