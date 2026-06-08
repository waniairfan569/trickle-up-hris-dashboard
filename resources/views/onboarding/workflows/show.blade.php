@extends('layouts.hr-app')

@section('title', 'Edit Workflow Tasks')
@section('breadcrumb', 'Workflow Tasks')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                <a href="{{ route('onboarding.workflows.index') }}" class="text-brand-600 hover:underline">Workflows</a> / 
                {{ $onboarding_workflow->name }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Define the tasks that make up this workflow.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button x-data @click="$dispatch('open-modal', 'add-task')" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-slate-900 shadow hover:bg-slate-800 transition dark:bg-brand-600 dark:hover:bg-brand-700">
                <i data-lucide="plus" class="h-4 w-4"></i> Add Task
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

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <div class="p-6 bg-slate-50 border-b border-slate-100 flex justify-between items-center dark:bg-slate-900/50 dark:border-slate-700/60">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider dark:text-white">Task Sequence</h3>
            <span class="text-xs text-slate-500">Tasks are created relative to the employee's start date.</span>
        </div>
        
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($onboarding_workflow->taskTemplates as $task)
                <div class="p-4 hover:bg-slate-50 transition flex items-center gap-4 group dark:hover:bg-slate-800/50">
                    <div class="cursor-grab text-slate-400 hover:text-slate-600">
                        <i data-lucide="grip-vertical" class="h-5 w-5"></i>
                    </div>
                    
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                        <div class="md:col-span-5">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                {{ $task->title }}
                                @if($task->is_required)
                                    <span class="inline-block w-2 h-2 rounded-full bg-rose-500" title="Required"></span>
                                @endif
                            </h4>
                            @if($task->description)
                                <p class="text-xs text-slate-500 truncate mt-0.5">{{ $task->description }}</p>
                            @endif
                        </div>
                        
                        <div class="md:col-span-3 text-xs font-bold">
                            @if($task->assigned_to_role === 'employee')
                                <span class="text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded">Assigned to: Employee</span>
                            @elseif($task->assigned_to_role === 'manager')
                                <span class="text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-1 rounded">Assigned to: Manager</span>
                            @else
                                <span class="text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-500/10 px-2 py-1 rounded">Assigned to: HR Admin</span>
                            @endif
                        </div>
                        
                        <div class="md:col-span-3 text-xs text-slate-500 font-medium">
                            Due: Start Date + {{ $task->due_days_from_start }} days
                        </div>
                        
                        <div class="md:col-span-1 text-right">
                            <form action="{{ route('onboarding.workflows.tasks.destroy', [$onboarding_workflow, $task]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-rose-600 transition" onclick="return confirm('Remove this task from the workflow?');">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">
                    No tasks defined yet. Add the first task to build the workflow.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Add Task Modal -->
    <div x-data="{ open: false }" @open-modal.window="if ($event.detail === 'add-task') open = true" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-show="open" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" x-show="open"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-slate-800 border border-slate-200 dark:border-slate-700" @click.away="open = false">
                    <form action="{{ route('onboarding.workflows.tasks.store', $onboarding_workflow) }}" method="POST">
                        @csrf
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 dark:bg-slate-800">
                            <h3 class="text-lg font-bold leading-6 text-slate-900 dark:text-white mb-4" id="modal-title">Add Task to Workflow</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Task Title <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Description</label>
                                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Assign To <span class="text-red-500">*</span></label>
                                        <select name="assigned_to_role" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            <option value="employee">The Employee</option>
                                            <option value="manager">Their Manager</option>
                                            <option value="hr_admin">HR Admin</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Due (Days from Start)</label>
                                        <input type="number" name="due_days_from_start" value="1" min="0" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    </div>
                                </div>
                                <div class="pt-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_required" value="1" checked class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">This task is required</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700/60">
                            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-brand-600 px-3 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 sm:ml-3 sm:w-auto">Add Task</button>
                            <button type="button" @click="open = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
