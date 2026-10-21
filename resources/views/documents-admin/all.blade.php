@extends('layouts.hr-app')

@section('title', 'All documents')
@section('breadcrumb', 'Documents')

@section('content')
<div class="space-y-5">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('documents-admin.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Documents
            </a>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Everything published to staff</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Policies and company documents in one place, whichever they are.
            </p>
        </div>
        <a href="{{ route('documents-admin.create') }}" class="btn-brand">
            <i data-lucide="plus" class="h-4 w-4"></i> Create a document
        </a>
    </div>

    {{-- Search and the one filter that matters: what it asks of people. --}}
    <form method="GET" action="{{ route('documents-admin.all') }}" class="flex flex-wrap items-center gap-2.5">
        <div class="relative min-w-[200px] flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search by name"
                   class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </div>
        <select name="needs" onchange="this.form.submit()"
                class="rounded-xl border border-slate-300 py-2.5 px-3 text-sm font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            <option value="all" @selected($needs === 'all')>Anything</option>
            <option value="acknowledgement" @selected($needs === 'acknowledgement')>Needs acknowledgement</option>
            <option value="signature" @selected($needs === 'signature')>Needs a signature</option>
            <option value="read" @selected($needs === 'read')>Read only</option>
        </select>
        <span class="text-xs font-semibold text-slate-400">{{ $rows->count() }} {{ Str::plural('item', $rows->count()) }}</span>
        @if($search !== '' || $needs !== 'all')
            <a href="{{ route('documents-admin.all') }}" class="text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">Clear</a>
        @endif
        <noscript><button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs dark:border-slate-600">Go</button></noscript>
    </form>

    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-2.5">Name</th>
                        <th class="px-5 py-2.5 whitespace-nowrap">Needs back</th>
                        <th class="px-5 py-2.5 whitespace-nowrap">Progress</th>
                        <th class="px-5 py-2.5 whitespace-nowrap">Holds</th>
                        <th class="px-5 py-2.5 whitespace-nowrap text-right">Open</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($rows as $r)
                        <tr class="align-top {{ $r['active'] ? '' : 'opacity-60' }}">
                            <td class="px-5 py-3">
                                <span class="font-bold text-slate-800 dark:text-white">{{ $r['title'] }}</span>
                                @unless($r['active'])
                                    <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">Inactive</span>
                                @endunless
                                @if($r['description'])
                                    <span class="mt-0.5 block text-xs text-slate-400">{{ Str::limit($r['description'], 90) }}</span>
                                @endif
                                <span class="mt-1 block text-[11px] text-slate-400">
                                    {{ $r['kind'] }}@if($r['version']) · v{{ $r['version'] }}@endif · created {{ optional($r['created_at'])->format('d M Y') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @php
                                    $tone = match($r['needs_key']) {
                                        'signature' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
                                        'acknowledgement' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                                        default => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300',
                                    };
                                @endphp
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $tone }}">{{ $r['needs'] }}</span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if($r['needs_key'] === 'read')
                                    <span class="text-xs text-slate-400">—</span>
                                @elseif($r['total'] === 0)
                                    <span class="text-xs text-slate-400">Not sent yet</span>
                                @else
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $r['percent'] }}%</span>
                                    <span class="text-[11px] text-slate-400"> · {{ $r['done'] }} of {{ $r['total'] }}</span>
                                    <span class="mt-1 block h-1.5 w-28 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                        <span class="block h-full rounded-full bg-brand-500" style="width: {{ $r['percent'] }}%"></span>
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">{{ $r['holds'] }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-right">
                                <a href="{{ $r['view'] }}" class="text-xs font-semibold text-brand-600 hover:underline dark:text-brand-400">View</a>
                                <a href="{{ $r['edit'] }}" class="ml-2.5 text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">
                            Nothing matches that.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
