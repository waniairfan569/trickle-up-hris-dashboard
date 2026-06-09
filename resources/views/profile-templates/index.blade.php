@extends('layouts.hr-app')

@section('title', 'Profile Templates')
@section('breadcrumb', 'Manage Profile Templates')

@section('content')
<div class="space-y-8">
    <!-- Header Hero Banner -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight dark:text-white">Profile Templates</h1>
            <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">Configure and deploy custom profile fields and sections across your organization.</p>
        </div>
        <div>
            <a href="{{ route('profile-templates.create') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 hover:shadow-lg transition duration-150">
                <i data-lucide="plus-circle" class="h-4.5 w-4.5"></i>
                <span>Create New Template</span>
            </a>
        </div>
    </div>

    <!-- Notifications -->
    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm dark:bg-emerald-950/20 dark:border-emerald-800 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse($templates as $template)
            @php
                $isDefault = $template->type === 'default';
                // The default template applies to everyone automatically, so its
                // "reach" is the whole active directory, not a pivot count.
                $assignedCount = $isDefault ? \App\Models\Employee::count() : $template->employees()->count();
            @endphp
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md dark:bg-slate-800 dark:border-slate-850 flex flex-col justify-between min-h-[300px]">
                
                <!-- Accent Line -->
                <div class="absolute top-0 left-0 right-0 h-1.5 {{ $isDefault ? 'bg-gradient-to-r from-blue-500 to-brand-500' : 'bg-gradient-to-r from-slate-400 to-indigo-500' }}"></div>

                <div class="p-6 space-y-4">
                    <!-- Title & Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white group-hover:text-brand-600 transition dark:group-hover:text-brand-400">{{ $template->name }}</h3>
                        
                        @if($isDefault)
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-950">
                                Default
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-650 border border-slate-200/60 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-800">
                                Dynamic
                            </span>
                        @endif
                    </div>

                    <!-- Description -->
                    <p class="text-xs text-slate-450 dark:text-slate-400 leading-relaxed line-clamp-3">
                        {{ $isDefault ? 'Automatically applied to every employee — no assignment needed.' : ($template->description ?? 'No description provided.') }}
                    </p>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-3 gap-3 border-t border-slate-100 pt-4 dark:border-slate-700/60 text-center">
                        <div class="space-y-0.5">
                            <span class="block text-xs font-semibold text-slate-400">Sections</span>
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $template->sections_count ?? 0 }}</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="block text-xs font-semibold text-slate-400">Total Fields</span>
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $template->fields_count ?? 0 }}</span>
                        </div>
                        <div class="space-y-0.5">
                            <span class="block text-xs font-semibold text-slate-400">{{ $isDefault ? 'Applies to' : 'Assigned' }}</span>
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $isDefault ? 'All (' . $assignedCount . ')' : $assignedCount }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card Actions -->
                <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 dark:bg-slate-850 dark:border-slate-750 flex items-center justify-between gap-3 mt-auto">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('profile-templates.show', $template->id) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                            <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                            <span>View details</span>
                        </a>
                        @if(!$isDefault)
                            <a href="{{ route('profile-templates.edit', $template->id) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                                <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                <span>Edit info</span>
                            </a>
                        @endif
                    </div>
                    
                    @if(!$isDefault)
                        <form action="{{ route('profile-templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this template and all its section definitions? This action is permanent and unassigns all employees.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-100 bg-rose-50 text-rose-600 hover:bg-rose-100 hover:text-rose-700 transition dark:bg-rose-950/20 dark:border-rose-900/30 dark:text-rose-400">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </form>
                    @endif
                </div>

            </div>
        @empty
            <div class="col-span-full bg-white border border-slate-200/80 rounded-2xl p-12 text-center shadow-sm dark:bg-slate-800 dark:border-slate-850 space-y-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 mx-auto dark:bg-indigo-500/10 dark:text-indigo-400">
                    <i data-lucide="layers" class="h-6 w-6"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No Templates Found</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">Get started by creating a new custom dynamic profile template for specific departments or locations.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
