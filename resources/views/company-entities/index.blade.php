@extends('layouts.hr-app')

@section('title', 'Company Entities')
@section('breadcrumb', 'Company Entities')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Company Entities</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage top-level legal entities, subsidiaries, and geographic locations.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="{{ route('company-entities.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                Add Entity
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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($entities as $entity)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80 flex flex-col transition hover:shadow-md hover:border-slate-300 dark:hover:border-slate-600">
                <div class="p-6 flex-1">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden dark:bg-slate-900 dark:border-slate-700">
                                @if($entity->logo)
                                    <img src="{{ Storage::url($entity->logo) }}" alt="{{ $entity->name }}" class="h-full w-full object-cover">
                                @else
                                    <i data-lucide="building-2" class="h-6 w-6 text-slate-400 dark:text-slate-500"></i>
                                @endif
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    {{ $entity->name }}
                                </h3>
                                <div class="text-xs font-medium text-slate-500 flex items-center gap-1 mt-0.5">
                                    <span>{{ $entity->country }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $entity->currency }}</span>
                                </div>
                            </div>
                        </div>
                        
                        @if($entity->is_primary)
                            <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-1 text-xs font-bold text-brand-700 ring-1 ring-inset ring-brand-700/10 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20">
                                Primary
                            </span>
                        @endif
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-700/60 mt-4">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Employees</p>
                            <p class="text-lg font-semibold text-slate-900 dark:text-white">{{ $entity->employees_count }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Status</p>
                            <p class="text-sm font-semibold {{ $entity->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500' }}">
                                {{ $entity->is_active ? 'Active' : 'Inactive' }}
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-slate-50/50 px-6 py-3 border-t border-slate-100 dark:bg-slate-900/50 dark:border-slate-700/60 flex items-center justify-between">
                    @if(!$entity->is_primary && $entity->is_active)
                        <form action="{{ route('company-entities.set-primary', $entity) }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs font-bold text-slate-500 hover:text-brand-600 dark:hover:text-brand-400 transition">
                                Set as Primary
                            </button>
                        </form>
                    @else
                        <div></div>
                    @endif
                    
                    <div class="flex gap-3">
                        <a href="{{ route('company-entities.show', $entity) }}" class="text-xs font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">View</a>
                        <a href="{{ route('company-entities.edit', $entity) }}" class="text-xs font-bold text-brand-600 hover:text-brand-900 dark:text-brand-400 dark:hover:text-brand-300 transition">Edit</a>
                    </div>
                </div>
            </div>
        @empty
            {{-- Medium #3: Empty state UI --}}
            <div class="col-span-1 md:col-span-2 lg:col-span-3">
                <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white p-16 text-center dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-700">
                        <i data-lucide="building-2" class="h-8 w-8 text-slate-400"></i>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-slate-900 dark:text-white">No company entities yet</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 max-w-sm">
                        Company entities represent your legal subsidiaries, branch offices, or geographic locations. Add your first entity to get started.
                    </p>
                    <a href="{{ route('company-entities.create') }}" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 transition">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        Add Your First Entity
                    </a>
                </div>
            </div>
        @endforelse
    </div>

    @if($entities->hasPages())
        <div class="mt-6">
            {{ $entities->links() }}
        </div>
    @endif
</div>
@endsection
