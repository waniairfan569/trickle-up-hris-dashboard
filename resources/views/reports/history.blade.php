@extends('layouts.hr-app')

@section('title', 'Report History')
@section('breadcrumb', 'Report History')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="reportHistory()">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="history" class="h-6 w-6 text-brand-500"></i> Report History
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Every report that has been generated — re-open, re-download, annotate or remove.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.generate') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-400 transition">
                <i data-lucide="plus" class="h-4 w-4"></i> New report
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif

    {{-- Stat cards --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total generated</p>
            <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1 tabular-nums">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Downloaded</p>
            <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1 tabular-nums">{{ $stats['downloaded'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Preview only</p>
            <p class="text-2xl font-extrabold text-slate-500 dark:text-slate-300 mt-1 tabular-nums">{{ $stats['previewed'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('reports.history') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Search</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Period, employee or note…" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Scope</label>
            <select name="scope" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All</option>
                <option value="single" @selected($scope==='single')>Single employee</option>
                <option value="all" @selected($scope==='all')>All employees</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Period type</label>
            <select name="type" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All</option>
                <option value="monthly" @selected($type==='monthly')>Monthly</option>
                <option value="mid_year" @selected($type==='mid_year')>Mid-year</option>
                <option value="yearly" @selected($type==='yearly')>Full year</option>
                <option value="custom" @selected($type==='custom')>Custom</option>
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-bold text-slate-500 mb-1">Output</label>
            <select name="output" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All</option>
                <option value="downloaded" @selected($output==='downloaded')>Downloaded</option>
                <option value="preview" @selected($output==='preview')>Preview only</option>
            </select>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
            <i data-lucide="filter" class="h-4 w-4"></i> Filter
        </button>
        @if($q || $scope || $type || $output)
            <a href="{{ route('reports.history') }}" class="text-sm text-slate-400 hover:text-slate-600 underline">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-700 text-left text-[11px] uppercase tracking-wider text-slate-400">
                    <th class="px-4 py-3 font-bold">Period</th>
                    <th class="px-4 py-3 font-bold">Report</th>
                    <th class="px-4 py-3 font-bold">Output</th>
                    <th class="px-4 py-3 font-bold">Generated</th>
                    <th class="px-4 py-3 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr class="border-b border-slate-50 dark:border-slate-700/50 hover:bg-slate-50/60 dark:hover:bg-slate-700/20 align-top">
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-800 dark:text-white">{{ $r->period_label }}</div>
                            @if($r->note)
                                <div class="mt-1 inline-flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400">
                                    <i data-lucide="sticky-note" class="h-3 w-3"></i> {{ $r->note }}
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-200">
                                <i data-lucide="{{ $r->report_scope==='all' ? 'users' : 'user' }}" class="h-3.5 w-3.5 text-brand-500"></i>
                                <span class="font-semibold">{{ $r->scope_label }}</span>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5">{{ $r->type_label }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if($r->was_downloaded)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                    <i data-lucide="download" class="h-3 w-3"></i> Downloaded
                                    @if($r->downloads_count > 1)<span class="opacity-70">×{{ $r->downloads_count }}</span>@endif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">
                                    <i data-lucide="eye" class="h-3 w-3"></i> Preview only
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-slate-700 dark:text-slate-200">{{ $r->created_at->format('d M Y · H:i') }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">by {{ optional($r->generatedBy)->full_name ?? 'System' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('reports.history.preview', $r) }}" target="_blank" title="Preview"
                                   class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-brand-600 dark:hover:bg-slate-700">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                </a>
                                <a href="{{ route('reports.history.download', $r) }}" title="Download PDF"
                                   class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-emerald-600 dark:hover:bg-slate-700">
                                    <i data-lucide="download" class="h-4 w-4"></i>
                                </a>
                                <button type="button" title="Add / edit note"
                                        @click="openNote({{ $r->id }}, @js($r->note))"
                                        class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-amber-600 dark:hover:bg-slate-700">
                                    <i data-lucide="sticky-note" class="h-4 w-4"></i>
                                </button>
                                <form method="POST" action="{{ route('reports.history.destroy', $r) }}" onsubmit="return confirm('Remove this report from history?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"
                                            class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-16 text-center">
                            <i data-lucide="history" class="h-10 w-10 text-slate-300 dark:text-slate-600 mx-auto"></i>
                            <p class="mt-3 font-bold text-slate-600 dark:text-slate-300">No reports yet</p>
                            <p class="text-sm text-slate-400 mt-1">Reports you generate — previewed or downloaded — will be logged here.</p>
                            <a href="{{ route('reports.generate') }}" class="inline-flex items-center gap-2 mt-4 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400">
                                <i data-lucide="plus" class="h-4 w-4"></i> Generate a report
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Footer: pagination + clear all --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>{{ $rows->links() }}</div>
        @if($stats['total'] > 0)
            <form method="POST" action="{{ route('reports.history.clear') }}" onsubmit="return confirm('Clear ALL {{ $stats['total'] }} report(s) from history? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 px-4 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">
                    <i data-lucide="trash" class="h-4 w-4"></i> Clear all history
                </button>
            </form>
        @endif
    </div>

    {{-- Note modal --}}
    <div x-show="noteOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @keydown.escape.window="noteOpen=false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" @click.away="noteOpen=false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="sticky-note" class="h-5 w-5 text-amber-500"></i> Report note
            </h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Add context — why it was run, who asked for it, etc.</p>
            <form :action="noteAction" method="POST" class="mt-4">
                @csrf @method('PUT')
                <textarea name="note" x-model="noteText" rows="3" maxlength="255" placeholder="e.g. Requested by finance for Q2 payroll"
                          class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button type="button" @click="noteOpen=false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400">Save note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function reportHistory() {
        return {
            noteOpen: false,
            noteText: '',
            noteAction: '',
            openNote(id, note) {
                this.noteText = note || '';
                this.noteAction = '{{ url('reports/history') }}/' + id;
                this.noteOpen = true;
            },
        };
    }
</script>
@endsection
