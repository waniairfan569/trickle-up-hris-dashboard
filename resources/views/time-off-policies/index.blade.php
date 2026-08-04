@extends('layouts.hr-app')

@section('title', 'Time-Off Policies')
@section('breadcrumb', 'Time-Off Policies')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Time-Off Policies</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Define the rules for leave (annual, sick, unpaid) and manage balances.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="{{ route('time-off-policies.balances-overview') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700 transition duration-150">
                <i data-lucide="table-2" class="h-4 w-4"></i>
                Leave Balances
            </a>
            <a href="{{ route('time-off-policies.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                New Policy
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3"><p class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('success') }}</p></div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700/80">
            <dt class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Policies</dt>
            <dd class="mt-2 text-3xl font-extrabold text-slate-900 dark:text-white">{{ collect($policies)->count() }}</dd>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($policies as $policy)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700/80 relative transition hover:shadow-md">
                
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-4">
                        @php
                            $colors = [
                                'annual' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400',
                                'sick' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-400',
                                'unpaid' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300',
                                'maternity' => 'bg-purple-100 text-purple-800 dark:bg-purple-500/20 dark:text-purple-400',
                                'paternity' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/20 dark:text-indigo-400',
                                'bereavement' => 'bg-slate-800 text-white dark:bg-slate-900 dark:text-slate-300',
                                'custom' => 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-400',
                            ];
                            $colorClass = $colors[$policy->type] ?? $colors['custom'];
                        @endphp
                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $colorClass }}">
                            {{ ucfirst($policy->type) }}
                        </span>
                        
                        @if(!$policy->is_active)
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">Inactive</span>
                        @endif
                    </div>
                    
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $policy->name }}</h3>
                    @if($policy->description)
                        <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-2 mb-4">{{ $policy->description }}</p>
                    @endif

                    <div class="grid grid-cols-2 gap-4 my-6 bg-slate-50 p-4 rounded-xl dark:bg-slate-900">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Allowance</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ (float) $policy->days_per_year }} days</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Accrual</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ ucfirst($policy->accrual_type) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Carry Over</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $policy->carry_over ? 'Yes' : 'No' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Approval</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                @if($policy->requires_approval)
                                    {{ ucfirst(str_replace('_', ' ', $policy->approval_type)) }}
                                @else
                                    Auto-approved
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400 pb-4">
                    <div class="flex items-center gap-2">
                        <i data-lucide="users" class="h-4 w-4"></i>
                        <span class="font-medium">{{ $policy->employees_count }} employees</span>
                    </div>
                    <a href="{{ route('time-off-policies.balances', $policy) }}" class="font-bold text-brand-600 hover:text-brand-800 transition dark:text-brand-400">View Balances</a>
                </div>

                <div class="border-t border-slate-100 flex items-center justify-between dark:border-slate-700/60 pt-4">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('time-off-policies.edit', $policy) }}" class="p-1.5 text-slate-400 hover:text-brand-600 transition rounded-lg hover:bg-brand-50 dark:hover:bg-slate-700 dark:hover:text-brand-400">
                            <i data-lucide="settings" class="h-4 w-4"></i>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="file-check-2" class="h-6 w-6 text-slate-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No policies found</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a time-off policy.</p>
                <div class="mt-6">
                    <a href="{{ route('time-off-policies.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 transition">
                        <i data-lucide="plus" class="h-4 w-4"></i> Create Policy
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
