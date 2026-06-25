@extends('layouts.hr-app')

@section('title', 'Department Details')
@section('breadcrumb', 'Department')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{ tab: 'employees' }">
    <!-- Header Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80 relative">
        <div class="h-4 w-full" style="background-color: {{ $department->color ?? '#94a3b8' }}"></div>
        <div class="p-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $department->name }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                        {{ $department->description ?? 'No description provided.' }}
                    </p>
                    
                    @if($department->parent)
                        <div class="mt-4 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <i data-lucide="corner-down-right" class="h-4 w-4"></i>
                            Sub-department of <a href="{{ route('departments.show', $department->parent) }}" class="font-bold text-brand-600 hover:underline dark:text-brand-400">{{ $department->parent->name }}</a>
                        </div>
                    @endif
                </div>
                
                <div class="flex flex-col items-start md:items-end gap-4">
                    <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100 dark:bg-slate-900 dark:border-slate-700">
                        @if($department->head)
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold text-sm shrink-0 dark:bg-brand-500/20 dark:text-brand-400">
                                {{ substr($department->head->first_name, 0, 1) }}{{ substr($department->head->last_name, 0, 1) }}
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Department Head</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $department->head->first_name }} {{ $department->head->last_name }}</p>
                            </div>
                        @else
                            <div class="h-10 w-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-500 shrink-0 dark:bg-amber-500/10">
                                <i data-lucide="user-x" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Department Head</p>
                                <p class="text-sm font-bold text-amber-600 dark:text-amber-500">Unassigned</p>
                            </div>
                        @endif
                    </div>
                    
                    <a href="{{ route('departments.edit', $department) }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                        <i data-lucide="pencil" class="h-4 w-4"></i>
                        Edit Department
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tabs Nav -->
        <div class="px-8 border-t border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
            <nav class="flex gap-6">
                <button @click="tab = 'employees'" :class="{'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400': tab === 'employees', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': tab !== 'employees'}" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-bold transition">
                    Employees ({{ $employees->total() }})
                </button>
                <button @click="tab = 'sub-departments'" :class="{'border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400': tab === 'sub-departments', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': tab !== 'sub-departments'}" class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-bold transition">
                    Sub-departments ({{ $department->children->count() }})
                </button>
            </nav>
        </div>
    </div>

    <!-- Employees Tab -->
    <div x-show="tab === 'employees'" style="display: none;" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Employee</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Role</th>
                        <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($employees as $emp)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ substr($emp->first_name, 0, 1) }}{{ substr($emp->last_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-900 dark:text-white">{{ $emp->first_name }} {{ $emp->last_name }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $emp->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                @if($emp->roles->isNotEmpty())
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ $emp->roles->first()->name }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('employees.profile', $emp) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800 dark:text-brand-400 transition">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                No employees directly assigned to this department yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($employees->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                {{ $employees->links() }}
            </div>
        @endif
    </div>

    <!-- Sub-departments Tab -->
    <div x-show="tab === 'sub-departments'" style="display: none;" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($department->children as $child)
                <li class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                    <div class="px-6 py-5 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4 flex-1">
                            <span class="h-3 w-3 rounded-full" style="background-color: {{ $child->color ?? $department->color ?? '#94a3b8' }}"></span>
                            <div>
                                <a href="{{ route('departments.show', $child) }}" class="text-base font-bold text-slate-900 dark:text-white hover:text-brand-600 transition">
                                    {{ $child->name }}
                                </a>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-6 w-1/2 justify-end">
                            <div class="hidden md:flex items-center gap-3 w-48 shrink-0">
                                @if($child->head)
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-700 font-bold text-xs shrink-0 dark:bg-slate-700 dark:text-slate-300">
                                        {{ substr($child->head->first_name, 0, 1) }}{{ substr($child->head->last_name, 0, 1) }}
                                    </div>
                                    <div class="truncate">
                                        <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $child->head->first_name }} {{ $child->head->last_name }}</p>
                                    </div>
                                @endif
                            </div>
                            
                            <div class="shrink-0 text-right">
                                <a href="{{ route('departments.edit', $child) }}" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition">Edit</a>
                            </div>
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-6 py-12 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">This department has no sub-departments.</p>
                </li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
