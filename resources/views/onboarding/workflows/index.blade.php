@extends('layouts.hr-app')

@section('title', 'Manage Workflows')
@section('breadcrumb', 'Onboarding Workflows')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('onboarding.index') }}" class="text-brand-600 hover:underline">Onboarding</a> / Workflows
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Design the task sequences for new hires and transitions.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button x-data @click="$dispatch('open-modal', 'create-workflow')" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition">
                <i data-lucide="plus" class="h-4 w-4"></i> New Workflow
            </button>
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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($workflows as $workflow)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700/80 transition hover:shadow-md">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $workflow->name }}</h3>
                    @if(!$workflow->is_active)
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">Inactive</span>
                    @endif
                </div>
                
                @if($workflow->description)
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6 line-clamp-2">{{ $workflow->description }}</p>
                @endif
                
                <div class="mt-auto grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 dark:bg-slate-900 dark:border-slate-700/60 text-center">
                        <div class="text-xl font-extrabold text-slate-900 dark:text-white">{{ $workflow->task_templates_count }}</div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase">Tasks</div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-100 dark:bg-slate-900 dark:border-slate-700/60 text-center">
                        <div class="text-xl font-extrabold text-brand-600 dark:text-brand-400">{{ $workflow->onboardings_count }}</div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase">Active</div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-between items-center dark:border-slate-700/60">
                    <div class="text-xs text-slate-500 font-medium">Trigger: {{ str_replace('_', ' ', ucfirst($workflow->trigger_type)) }}</div>
                    <a href="{{ route('onboarding.workflows.show', $workflow) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800 transition dark:text-brand-400">Edit Tasks <i data-lucide="arrow-right" class="h-3 w-3 inline"></i></a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="layout-template" class="h-6 w-6 text-slate-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No workflows defined</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a new onboarding workflow.</p>
            </div>
        @endforelse
    </div>

    <!-- Create Workflow Modal -->
    <div x-data="{ open: false }" @open-modal.window="if ($event.detail === 'create-workflow') open = true" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-show="open" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" x-show="open"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-slate-800 border border-slate-200 dark:border-slate-700" @click.away="open = false">
                    <form action="{{ route('onboarding.workflows.store') }}" method="POST">
                        @csrf
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 dark:bg-slate-800">
                            <h3 class="text-lg font-bold leading-6 text-slate-900 dark:text-white mb-4" id="modal-title">Create Workflow</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Name</label>
                                    <input type="text" name="name" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Description</label>
                                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Trigger Type</label>
                                    <select name="trigger_type" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                        <option value="manual">Manual (HR assigns manually)</option>
                                        <option value="auto_on_hire">Automatic on hire</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700/60">
                            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-brand-600 px-3 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 sm:ml-3 sm:w-auto">Create</button>
                            <button type="button" @click="open = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
