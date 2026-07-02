@extends('layouts.hr-app')

@section('title', 'Attendance Mode')
@section('breadcrumb', 'Administration > Attendance Mode')

@section('content')
<div class="max-w-5xl mx-auto space-y-6"
     x-data="{
        selected: [],
        get allIds() { return Array.from(document.querySelectorAll('.emp-check')).map(c => c.value); },
        toggleAll(e) { this.selected = e.target.checked ? this.allIds : []; },
     }">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="fingerprint" class="h-6 w-6 text-brand-500"></i> Attendance Mode
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Select employees and set them all to Biometric or Remote at once.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <!-- Filters -->
    <form method="GET" class="flex flex-wrap items-end gap-3 bg-white rounded-xl border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or email…" class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Current mode</label>
            <select name="mode" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="">All</option>
                <option value="biometric" {{ request('mode') === 'biometric' ? 'selected' : '' }}>On-site · Biometric</option>
                <option value="remote" {{ request('mode') === 'remote' ? 'selected' : '' }}>Remote · Dashboard</option>
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold px-4 py-2">Filter</button>
        @if(request('search') || request('mode'))
            <a href="{{ route('employees.attendance-mode') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Clear</a>
        @endif
    </form>

    <form method="POST" action="{{ route('employees.attendance-mode.bulk') }}">
        @csrf
        <!-- Sticky bulk action bar -->
        <div class="flex items-center justify-between gap-3 flex-wrap bg-white rounded-xl border border-slate-200 px-4 py-3 mb-3 dark:bg-slate-800 dark:border-slate-700">
            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                <span x-text="selected.length"></span> selected
            </span>
            <div class="flex items-center gap-2">
                <button type="submit" name="attendance_mode" value="biometric" :disabled="selected.length === 0"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    <i data-lucide="fingerprint" class="h-4 w-4"></i> Set to On-site · Biometric
                </button>
                <button type="submit" name="attendance_mode" value="remote" :disabled="selected.length === 0"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    <i data-lucide="monitor" class="h-4 w-4"></i> Set to Remote · Dashboard
                </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 text-slate-500 font-medium uppercase text-xs dark:bg-slate-900/40">
                    <tr>
                        <th class="px-4 py-3 w-10"><input type="checkbox" @change="toggleAll($event)" class="rounded border-slate-300 text-brand-600"></th>
                        <th class="px-4 py-3">Employee</th>
                        <th class="px-4 py-3">Device UID</th>
                        <th class="px-4 py-3">Current mode</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($employees as $emp)
                        @php $mode = $emp->attendance_mode ?? 'biometric'; @endphp
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-4 py-3"><input type="checkbox" class="emp-check rounded border-slate-300 text-brand-600" name="user_ids[]" value="{{ $emp->id }}" x-model="selected"></td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 dark:text-white">{{ trim($emp->first_name . ' ' . $emp->last_name) }}</div>
                                <div class="text-xs text-slate-400">{{ $emp->email }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $emp->zkteco_uid ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($mode === 'remote')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10">Remote · Dashboard</span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-500/10">On-site · Biometric</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">No employees match your filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</div>
@endsection
