@extends('layouts.hr-app')

@section('title', 'Report Center')
@section('breadcrumb', 'Reports')

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Report Center</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Headcount, distribution, and an exportable employee report.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.attendance') }}" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                <i data-lucide="clock" class="h-4 w-4"></i> Attendance report
            </a>
            <a href="{{ route('employees.export') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition">
                <i data-lucide="download" class="h-4 w-4"></i> Download Employee CSV
            </a>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $cards = [
                ['Total Employees', $total, 'users', 'text-brand-600 bg-brand-50 dark:bg-brand-500/10'],
                ['Active', $active, 'user-check', 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10'],
                ['Pending Invites', $pending, 'mail', 'text-amber-600 bg-amber-50 dark:bg-amber-500/10'],
                ['New Hires (30d)', $recentHires, 'user-plus', 'text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $icon, $tint])
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $tint }}"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                </div>
                <div class="mt-3 text-3xl font-extrabold text-slate-900 dark:text-white">{{ $value }}</div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-0.5">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <!-- Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach(['Headcount by Department' => $byDepartment, 'Headcount by Entity' => $byEntity] as $title => $data)
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
                <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-4">{{ $title }}</h2>
                @if($data->isEmpty())
                    <p class="text-xs text-slate-400 italic">No data.</p>
                @else
                    <div class="space-y-3">
                        @foreach($data as $name => $count)
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $name }}</span>
                                    <span class="font-bold text-slate-500">{{ $count }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $total > 0 ? round($count / $total * 100) : 0 }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Employee details report -->
    <div class="overflow-hidden rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Employee Details ({{ $employees->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-450 dark:bg-slate-900/40 dark:border-slate-700/60">
                        <th class="py-3 px-6">Name</th>
                        <th class="py-3 px-6">Work Email</th>
                        <th class="py-3 px-6">Job Title</th>
                        <th class="py-3 px-6">Department</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6">Start Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 text-xs dark:divide-slate-700/60">
                    @forelse($employees as $e)
                        @php
                            $statusLabel = $e->account_status === 'invited' ? 'Pending' : ($e->account_status === 'active' ? 'Active' : ucfirst($e->account_status));
                            $statusClass = $e->account_status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($e->account_status === 'invited' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600');
                            $start = $e->hire_date ?? $e->joined_at;
                        @endphp
                        <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-750/30">
                            <td class="py-3 px-6 font-bold text-slate-800 dark:text-white">{{ $e->full_name }}</td>
                            <td class="py-3 px-6 text-slate-500">{{ $e->email }}</td>
                            <td class="py-3 px-6 text-slate-600 dark:text-slate-300">{{ $e->job_title ?: '—' }}</td>
                            <td class="py-3 px-6 text-slate-600 dark:text-slate-300">{{ optional($e->department)->name ?: 'Unassigned' }}</td>
                            <td class="py-3 px-6"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="py-3 px-6 text-slate-500">{{ $start ? \Carbon\Carbon::parse($start)->format('d M Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center text-slate-400 italic">No employees.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
