@extends('layouts.hr-app')

@section('title', 'System Audit Logs')
@section('breadcrumb', 'Security · Audit Logs')

@php
    $actionTone = [
        'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'updated' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
        'adjusted' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',
        'deleted' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'deactivated' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
    ];
    $entityLabel = fn ($e) => \Illuminate\Support\Str::of($e)->replace('_', ' ')->headline();
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">System Audit Logs</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Who did what, when, and from where — {{ number_format($total) }} recorded event{{ $total == 1 ? '' : 's' }}.</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.audit-logs') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Search</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="description or IP…" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Action</label>
            <select name="action" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="all">All</option>
                @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ ucfirst($a) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Type</label>
            <select name="entity_type" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="all">All</option>
                @foreach($entities as $e)<option value="{{ $e }}" @selected(request('entity_type')===$e)>{{ $entityLabel($e) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">User</label>
            <select name="user_id" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="all">Everyone</option>
                @foreach($users as $u)<option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->full_name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <button type="submit" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="search" class="h-4 w-4 inline"></i></button>
        @if(request()->hasAny(['q','action','entity_type','user_id','date_from','date_to']))
            <a href="{{ route('admin.audit-logs') }}" class="text-xs font-semibold text-slate-500 hover:text-rose-600">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-900/40">
                    <th class="px-5 py-3">When</th><th class="px-5 py-3">User</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Details</th><th class="px-5 py-3">IP</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30">
                            <td class="px-5 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $log->created_at->format('d M Y') }}</span>
                                <span class="block text-[11px]">{{ $log->created_at->format('H:i:s') }} · {{ $log->created_at->diffForHumans() }}</span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ optional($log->user)->full_name ?? 'System' }}</span>
                            </td>
                            <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $actionTone[$log->action] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">{{ ucfirst($log->action) }}</span></td>
                            <td class="px-5 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">{{ $entityLabel($log->entity_type) }}@if($log->entity_id)<span class="text-slate-400"> #{{ $log->entity_id }}</span>@endif</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300 max-w-md">{{ $log->description }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-[11px] font-mono text-slate-400">{{ $log->ip_address ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="scroll-text" class="h-6 w-6"></i></div>
                            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No audit events{{ request()->hasAny(['q','action','entity_type','user_id','date_from','date_to']) ? ' match these filters' : ' yet' }}</p>
                            <p class="text-xs text-slate-400 mt-1">Admin actions (creating, editing, adjusting records) are recorded here automatically.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
