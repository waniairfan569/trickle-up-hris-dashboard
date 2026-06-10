@extends('layouts.hr-app')

@section('title', 'Archived Employees')
@section('breadcrumb', 'Employees')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Archived Employees</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Deactivated employees. Restore them to the directory, or delete permanently.</p>
        </div>
        <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            <span>Back to Directory</span>
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-450 dark:bg-slate-900/40 dark:border-slate-700/60">
                        <th class="py-4 px-6">Name</th>
                        <th class="py-4 px-6">Department</th>
                        <th class="py-4 px-6">Job Title</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 text-xs dark:divide-slate-700/60">
                    @forelse($employees as $emp)
                        @if(!$emp->user) @continue @endif
                        @php $fullName = $emp->user->full_name ?? 'Unknown'; @endphp
                        <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-750/30 transition">
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    @if($emp->user->avatar_url)
                                        <img src="{{ $emp->user->avatar_url }}" alt="{{ $fullName }}" class="h-9 w-9 rounded-xl object-cover grayscale ring-1 ring-slate-100 dark:ring-slate-750">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-300 font-bold text-white dark:bg-slate-600">{{ $emp->user->initials ?? 'EM' }}</div>
                                    @endif
                                    <div>
                                        <span class="font-bold text-slate-700 dark:text-slate-200 block">{{ $fullName }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $emp->user->email ?? $emp->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6 text-slate-500">{{ $emp->department->name ?? 'Unassigned' }}</td>
                            <td class="py-4 px-6 text-slate-500">{{ $emp->job_title ?? '—' }}</td>
                            <td class="py-4 px-6 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <form action="{{ route('employees.restore', $emp->user_id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" title="Restore to directory"
                                                class="inline-flex items-center gap-1 rounded-xl bg-emerald-50 px-2.5 py-1.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-100 transition dark:bg-emerald-500/10 dark:text-emerald-400">
                                            <i data-lucide="rotate-ccw" class="h-3 w-3"></i> Restore
                                        </button>
                                    </form>
                                    <form action="{{ route('employees.destroy', $emp->user_id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Permanently delete {{ $fullName }}? This removes their profile, attendance, leave and all related records. This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete permanently"
                                                class="inline-flex items-center gap-1 rounded-xl bg-rose-50 px-2.5 py-1.5 text-[10px] font-bold text-rose-700 hover:bg-rose-100 transition dark:bg-rose-500/10 dark:text-rose-400">
                                            <i data-lucide="trash-2" class="h-3 w-3"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-750/50">
                                        <i data-lucide="archive" class="h-6 w-6"></i>
                                    </div>
                                    <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-250">No archived employees</h3>
                                    <p class="text-xs text-slate-400 mt-0.5">Deactivated employees will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
            {{ $employees->links() }}
        </div>
    </div>
</div>
@endsection
