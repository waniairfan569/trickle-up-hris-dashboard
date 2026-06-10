@extends('layouts.hr-app')

@section('title', 'Employee Directory')
@section('breadcrumb', 'Employees')

@section('content')
@php
    $auth = auth()->user();
@endphp

<div class="space-y-6">

    <!-- Directory Header & Action -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Employee Directory</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Browse, filter, and view employee profiles according to your organizational scope.</p>
        </div>
        
        <div class="flex items-center space-x-3">
            @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('hr_admin'))
                <button type="button" @click="$dispatch('open-import-modal')" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="upload" class="h-4 w-4"></i>
                    <span>Import</span>
                </button>
                <a href="{{ route('employees.export') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    <span>Export</span>
                </a>
                <a href="{{ route('employees.create') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    <span>Add New Employee</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700">
        <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            
            <!-- Search -->
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Search Employee</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i data-lucide="search" class="h-4 w-4 text-slate-400 dark:text-slate-500"></i>
                    </div>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="Search name, job title..." 
                           class="w-full text-xs font-semibold border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 bg-slate-50/50 text-slate-850 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
            </div>

            <!-- Department Filter -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Department</label>
                <select name="department" onchange="this.form.submit()"
                        class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Role Filter -->
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Role Permission</label>
                    <select name="role" onchange="this.form.submit()"
                            class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <option value="">All Roles</option>
                        @foreach($rolesMap as $slug => $name)
                            <option value="{{ $slug }}" {{ request('role') == $slug ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="hidden md:inline-flex items-center justify-center rounded-xl bg-slate-100 px-3 py-2.5 text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600">
                    <i data-lucide="filter" class="h-4 w-4"></i>
                </button>
            </div>
            
        </form>
    </div>

    <!-- Directory Sleek Table -->
    <div class="overflow-hidden rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-450 dark:bg-slate-900/40 dark:border-slate-700/60">
                        <th class="py-4 px-6">Name</th>
                        <th class="py-4 px-6">Department</th>
                        <th class="py-4 px-6">Job Title</th>
                        <th class="py-4 px-6">Manager</th>
                        <th class="py-4 px-6">RBAC Role</th>
                        <th class="py-4 px-6">Status</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 text-xs dark:divide-slate-700/60">
                    @forelse($employees as $emp)
                        @if(!$emp->user) @continue @endif
                        @php
                            $fullName = $emp->user->full_name ?? 'Unknown';
                            $title = $emp->job_title ?? 'Employee';
                            $deptName = $emp->department->name ?? 'Unassigned';
                            $roleSlug = $emp->user->role->slug ?? 'employee';
                            
                            $statusClasses = match(strtolower($emp->status ?? 'active')) {
                                'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                                'inactive' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                                default => 'bg-slate-50 text-slate-650'
                            };
                        @endphp
                        
                        <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-750/30 transition">
                            
                            <!-- Name -->
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    @if($emp->user->avatar_url)
                                        <img src="{{ $emp->user->avatar_url }}" alt="{{ $fullName }}" class="h-9 w-9 rounded-xl object-cover ring-1 ring-slate-100 dark:ring-slate-750">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-indigo-500 font-bold text-white shadow-sm shadow-indigo-500/10">
                                            {{ $emp->user->initials ?? 'EM' }}
                                        </div>
                                    @endif
                                    <div>
                                        <span class="font-bold text-slate-900 dark:text-white block hover:text-brand-600 transition">{{ $fullName }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $emp->user->email ?? $emp->email }}</span>
                                        @if($emp->user->jobLocation)
                                            <span class="mt-1 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                {{ $emp->user->jobLocation->is_remote ? '🏠' : '📍' }} {{ $emp->user->jobLocation->name }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Department -->
                            <td class="py-4 px-6">
                                @if($emp->department)
                                    @php
                                        $palette = ['#3B82F6','#10B981','#8B5CF6','#F59E0B','#EF4444','#06B6D4','#EC4899','#14B8A6','#6366F1','#F97316'];
                                        $dc = $emp->department->color ?: $palette[abs(crc32($emp->department->name)) % count($palette)];
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: {{ $dc }}1a; color: {{ $dc }};">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $dc }};"></span>
                                        {{ $emp->department->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">Unassigned</span>
                                @endif
                            </td>
                            
                            <!-- Job Title -->
                            <td class="py-4 px-6 text-slate-650 dark:text-slate-300 font-medium">
                                {{ $title }}
                            </td>

                            <!-- Manager -->
                            <td class="py-4 px-6">
                                @if($emp->user->manager)
                                    <a href="{{ route('employees.profile', $emp->user->manager_id) }}" class="inline-flex items-center gap-2 group">
                                        @if($emp->user->manager->avatar_url)
                                            <img src="{{ $emp->user->manager->avatar_url }}" alt="{{ $emp->user->manager->full_name }}" class="h-6 w-6 rounded-lg object-cover ring-1 ring-slate-100 dark:ring-slate-750">
                                        @else
                                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-[9px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ $emp->user->manager->initials ?? '–' }}</span>
                                        @endif
                                        <span class="font-semibold text-slate-700 group-hover:text-brand-600 transition dark:text-slate-300">{{ $emp->user->manager->full_name }}</span>
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <!-- Role Badge -->
                            <td class="py-4 px-6">
                                <x-role-badge :role="$emp->user->role" />
                            </td>
                            
                            <!-- Status -->
                            <td class="py-4 px-6">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold capitalize {{ $statusClasses }}">
                                    {{ $emp->status ?? 'active' }}
                                </span>
                            </td>
                            
                            <!-- Actions -->
                            <td class="py-4 px-6 text-right font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="{{ route('employees.profile', $emp->user_id) }}" class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-brand-600 transition dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-600 dark:hover:text-white">
                                            View
                                        </a>
                                    
                                    @if($auth->canEdit($emp->user))
                                        <a href="{{ route('employees.profile.edit', $emp->user_id) }}" class="rounded-xl bg-brand-50 px-2.5 py-1.5 text-[10px] font-bold text-brand-700 hover:bg-brand-100 hover:text-brand-800 transition dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
                                            Edit
                                        </a>
                                    @endif

                                    @if($auth->isAdmin() && $auth->id !== $emp->user_id)
                                        <form action="{{ route('employees.destroy', $emp->user_id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Delete {{ $fullName }}? This permanently removes their profile, attendance, leave and all related records. This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" title="Delete employee"
                                                    class="rounded-xl bg-rose-50 px-2.5 py-1.5 text-[10px] font-bold text-rose-700 hover:bg-rose-100 transition dark:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/20">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-750/50">
                                        <i data-lucide="users" class="h-6 w-6"></i>
                                    </div>
                                    <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-250">No employees found</h3>
                                    <p class="text-xs text-slate-400 mt-0.5">There are no records corresponding to your access scope.</p>
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

    <!-- Import Modal -->
    <div x-data="{ show: false }"
         x-show="show"
         @open-import-modal.window="show = true"
         class="relative z-50"
         style="display: none;"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div @click.away="show = false" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-slate-800">
                    <form action="{{ route('employees.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 dark:bg-slate-800">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 sm:mx-0 sm:h-10 sm:w-10 dark:bg-brand-500/20">
                                    <i data-lucide="upload-cloud" class="h-6 w-6 text-brand-600 dark:text-brand-400"></i>
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                    <h3 class="text-base font-bold leading-6 text-slate-900 dark:text-white" id="modal-title">Bulk Import Employees</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            Upload a CSV file containing employee data. <br>
                                            <span class="text-xs">It supports the full profile template including contact info, salary, contract type, etc. Ensure the first row contains standard header names. Columns can be in any order.</span>
                                        </p>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">CSV File</label>
                                        <input type="file" name="import_file" accept=".csv" required class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-400 dark:file:hover:bg-brand-500/20">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 dark:bg-slate-800/50">
                            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm hover:bg-brand-700 sm:ml-3 sm:w-auto">Import</button>
                            <button type="button" @click="show = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto dark:bg-slate-700 dark:text-white dark:ring-slate-600 dark:hover:bg-slate-600">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
