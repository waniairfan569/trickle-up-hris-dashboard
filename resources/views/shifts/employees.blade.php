@extends('layouts.hr-app')

@section('title', 'Shift Roster')
@section('breadcrumb', 'Shift Roster')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ q: '' }">

    <div>
        <a href="{{ route('shifts.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 transition dark:text-slate-400 dark:hover:text-white mb-3">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Shift Management
        </a>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="calendar-clock" class="h-6 w-6 text-brand-500"></i> {{ $shift->name }}
                    @if($shift->is_default)<span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Default</span>@endif
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ \Illuminate\Support\Str::of($shift->start_time)->substr(0,5) }} – {{ \Illuminate\Support\Str::of($shift->end_time)->substr(0,5) }}
                    · {{ implode(', ', $shift->working_days ?? []) }}
                    · <span class="font-semibold">{{ $members->count() }}</span> {{ \Illuminate\Support\Str::plural('employee', $members->count()) }} on this shift
                </p>
            </div>
            @if($members->isNotEmpty())
                <form method="POST" action="{{ route('shifts.unassign-all', $shift) }}" onsubmit="return confirm('Remove all {{ $members->count() }} employee(s) from the {{ addslashes($shift->name) }} shift? This cancels the whole roster.')" class="shrink-0">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:bg-slate-800 dark:border-rose-500/30 dark:text-rose-400 dark:hover:bg-rose-500/10">
                        <i data-lucide="user-x" class="h-4 w-4"></i> Remove all
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif

    @if($members->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="users" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No one is on this shift yet</p>
            <p class="text-xs text-slate-400 mt-1">Use “Assign to some” or “Assign to all” on the Shift Management page.</p>
        </div>
    @else
        <div class="relative max-w-sm">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
            <input type="text" x-model="q" placeholder="Search employee…" class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-4 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Employee</th>
                            <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Department</th>
                            <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">Assignment</th>
                            <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($members as $m)
                            @php
                                $u = $m['user'];
                                $name = trim($u->first_name . ' ' . $u->last_name) ?: 'Employee';
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/20"
                                x-show="q === '' || @js(strtolower($name . ' ' . ($u->email ?? ''))).includes(q.toLowerCase())">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        @if($u->avatar_url)
                                            <img src="{{ $u->avatar_url }}" class="h-8 w-8 rounded-lg object-cover" alt="">
                                        @else
                                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-indigo-500 text-[11px] font-bold text-white">{{ $u->initials }}</span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('employees.profile', $u->id) }}" class="block font-bold text-slate-800 dark:text-white hover:text-brand-600 truncate">{{ $name }}</a>
                                            @if($u->email)<span class="block text-[11px] text-slate-400 truncate">{{ $u->email }}</span>@endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ optional($u->department)->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($m['recurring'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            <i data-lucide="repeat" class="h-3 w-3"></i> Recurring
                                        </span>
                                        <span class="ml-1 text-[11px] text-slate-400">{{ implode(', ', $m['recurring']->recurring_days ?? []) }}</span>
                                    @endif
                                    @foreach($m['singles'] as $s)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            <i data-lucide="calendar" class="h-3 w-3"></i> {{ \Carbon\Carbon::parse($s->date)->format('d M Y') }}
                                        </span>
                                    @endforeach
                                    @if(!$m['recurring'] && $m['singles']->isEmpty())<span class="text-slate-300">—</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('shifts.unassign-employee', [$shift, $u->id]) }}" onsubmit="return confirm('Remove {{ addslashes($name) }} from the {{ addslashes($shift->name) }} shift?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-500 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 transition dark:border-slate-600 dark:text-slate-300 dark:hover:bg-rose-500/10">
                                            <i data-lucide="user-minus" class="h-3.5 w-3.5"></i> Unassign
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
