@extends('layouts.hr-app')

@section('title', 'Departments')
@section('breadcrumb', 'Departments')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Departments</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage your organizational structure and department heads.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="{{ route('departments.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Department
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3">
                    <p class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-brand-50 flex items-center justify-center dark:bg-brand-500/10">
                    <i data-lucide="network" class="h-6 w-6 text-brand-600 dark:text-brand-400"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Total Departments</p>
                    <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $totalDepartments }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-emerald-50 flex items-center justify-center dark:bg-emerald-500/10">
                    <i data-lucide="users" class="h-6 w-6 text-emerald-600 dark:text-emerald-400"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Total Employees</p>
                    <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $totalEmployees }}</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 dark:bg-slate-800 dark:border-slate-700/80">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-amber-50 flex items-center justify-center dark:bg-amber-500/10">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-amber-600 dark:text-amber-400"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Missing Heads</p>
                    <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $departmentsWithoutHead }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Medium #4: Contextual guidance when missing heads --}}
    @if($departmentsWithoutHead > 0)
    <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
        <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5"></i>
        <div>
            <p class="text-sm font-bold text-amber-800 dark:text-amber-400">
                {{ $departmentsWithoutHead }} department{{ $departmentsWithoutHead > 1 ? 's' : '' }} without a head
            </p>
            <p class="text-xs text-amber-700 dark:text-amber-500 mt-0.5">
                To assign a head, click the <strong>pencil (edit)</strong> icon next to the department, then choose a manager from the <em>Department Head</em> dropdown. Department heads appear as the first approver in leave and performance workflows.
            </p>
        </div>
    </div>
    @endif

    <!-- Tree List -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($departments as $dept)
                <!-- Parent Row -->
                <li class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                    <div class="px-6 py-5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $dept->color ?? '#94a3b8' }}"></span>
                            <div>
                                <a href="{{ route('departments.show', $dept) }}" class="text-base font-bold text-slate-900 dark:text-white hover:text-brand-600 transition">
                                    {{ $dept->name }}
                                </a>
                                @if(!$dept->is_active)
                                    <span class="ml-2 inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-800">Inactive</span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-6 w-1/2 justify-end">
                            <!-- Head Info -->
                            <div class="hidden md:flex items-center gap-3 w-48 shrink-0">
                                @if($dept->head)
                                    <div class="h-8 w-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold text-xs shrink-0 dark:bg-brand-500/20 dark:text-brand-400">
                                        {{ substr($dept->head->first_name, 0, 1) }}{{ substr($dept->head->last_name, 0, 1) }}
                                    </div>
                                    <div class="truncate">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $dept->head->first_name }} {{ $dept->head->last_name }}</p>
                                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Dept Head</p>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400">
                                            No head assigned
                                        </span>
                                        <a href="{{ route('departments.edit', $dept) }}" 
                                           title="Click Edit to assign a department head"
                                           class="text-xs font-bold text-brand-600 hover:text-brand-800 transition dark:text-brand-400">
                                            Assign →
                                        </a>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="w-24 shrink-0 text-right">
                                <span class="inline-flex items-center gap-1.5 text-sm font-bold text-slate-600 dark:text-slate-300">
                                    <i data-lucide="users" class="h-4 w-4 text-slate-400"></i>
                                    {{ $dept->allEmployeesCount() }}
                                </span>
                            </div>
                            
                            <div class="shrink-0 flex items-center gap-1">
                                <a href="{{ route('departments.create', ['parent' => $dept->id]) }}" title="Add a sub-department" class="inline-flex p-2 text-slate-400 hover:text-brand-600 transition hover:bg-brand-50 rounded-lg dark:hover:bg-slate-700 dark:hover:text-brand-400">
                                    <i data-lucide="folder-plus" class="h-4 w-4"></i>
                                </a>
                                <a href="{{ route('departments.edit', $dept) }}" title="Edit department" class="inline-flex p-2 text-slate-400 hover:text-brand-600 transition hover:bg-brand-50 rounded-lg dark:hover:bg-slate-700 dark:hover:text-brand-400">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </li>

                <!-- Children -->
                @foreach($dept->children as $child)
                    <li class="bg-slate-50/30 hover:bg-slate-50/80 dark:bg-slate-800/30 dark:hover:bg-slate-800/80 transition">
                        <div class="px-6 py-4 flex items-center justify-between gap-4 pl-14">
                            <div class="flex items-center gap-3 flex-1 relative">
                                <!-- Tree Line -->
                                <div class="absolute -left-5 top-1/2 h-px w-4 bg-slate-200 dark:bg-slate-700"></div>
                                <div class="absolute -left-5 -top-4 bottom-1/2 w-px bg-slate-200 dark:bg-slate-700"></div>
                                
                                <span class="h-2 w-2 rounded-full" style="background-color: {{ $child->color ?? $dept->color ?? '#94a3b8' }}"></span>
                                <div>
                                    <a href="{{ route('departments.show', $child) }}" class="text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-brand-600 transition">
                                        {{ $child->name }}
                                    </a>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-6 w-1/2 justify-end">
                                <div class="hidden md:flex items-center gap-3 w-48 shrink-0">
                                    @if($child->head)
                                        <div class="h-6 w-6 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold text-[10px] shrink-0 dark:bg-slate-700 dark:text-slate-300">
                                            {{ substr($child->head->first_name, 0, 1) }}{{ substr($child->head->last_name, 0, 1) }}
                                        </div>
                                        <div class="truncate">
                                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate">{{ $child->head->first_name }} {{ $child->head->last_name }}</p>
                                        </div>
                                    @endif
                                </div>
                                
                                <div class="w-24 shrink-0 text-right">
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                                        <i data-lucide="users" class="h-3 w-3"></i>
                                        {{ $child->allEmployeesCount() }}
                                    </span>
                                </div>
                                
                                <div class="shrink-0">
                                    <a href="{{ route('departments.edit', $child) }}" class="inline-flex p-1.5 text-slate-400 hover:text-brand-600 transition hover:bg-brand-50 rounded-lg dark:hover:bg-slate-700 dark:hover:text-brand-400">
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            @empty
                <div class="p-12 text-center">
                    <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-800">
                        <i data-lucide="network" class="h-6 w-6 text-slate-400"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">No departments found</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a new department.</p>
                </div>
            @endforelse
        </ul>
    </div>
</div>
@endsection
