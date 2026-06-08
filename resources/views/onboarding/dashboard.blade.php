@extends('layouts.hr-app')

@section('title', 'Onboarding Dashboard')
@section('breadcrumb', 'Onboarding')

@section('content')
<div class="max-w-7xl mx-auto space-y-8" x-data="{ activeTab: 'my_onboarding' }">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Onboarding</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage new hire setups, required tasks, and progress.
            </p>
        </div>
        @if(auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="{{ route('onboarding.workflows.index') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                <i data-lucide="settings" class="h-4 w-4 mr-2"></i> Manage Workflows
            </a>
            <button x-data @click="$dispatch('open-modal', 'start-onboarding')" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="play" class="h-4 w-4"></i>
                Start Onboarding
            </button>
        </div>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-400"></i>
                <div class="ml-3"><p class="text-sm font-medium text-emerald-800 dark:text-emerald-400">{{ session('success') }}</p></div>
            </div>
        </div>
    @endif

    <!-- Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-700">
        <nav class="-mb-px flex space-x-8">
            <button @click="activeTab = 'my_onboarding'" :class="{'border-brand-500 text-brand-600 dark:text-brand-400': activeTab === 'my_onboarding', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': activeTab !== 'my_onboarding'}" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition">
                My Onboarding
            </button>
            @if($teamOnboardings->isNotEmpty() || auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
                <button @click="activeTab = 'team_onboarding'" :class="{'border-brand-500 text-brand-600 dark:text-brand-400': activeTab === 'team_onboarding', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': activeTab !== 'team_onboarding'}" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition flex items-center">
                    Team Onboarding
                </button>
            @endif
            @if(auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
                <button @click="activeTab = 'all_onboarding'" :class="{'border-brand-500 text-brand-600 dark:text-brand-400': activeTab === 'all_onboarding', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 dark:text-slate-400 dark:hover:text-slate-300': activeTab !== 'all_onboarding'}" class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm transition">
                    All Onboarding (HR)
                </button>
            @endif
        </nav>
    </div>

    <!-- My Onboarding Tab -->
    <div x-show="activeTab === 'my_onboarding'">
        @if($myOnboarding)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col md:flex-row items-center gap-6 dark:bg-slate-800 dark:border-slate-700/80 transition hover:shadow-md relative overflow-hidden">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $myOnboarding->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-400' }}">
                            {{ str_replace('_', ' ', ucfirst($myOnboarding->status)) }}
                        </span>
                        @if($myOnboarding->isOverdue())
                            <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-500/20 dark:text-rose-400">Overdue Tasks</span>
                        @endif
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-1">{{ $myOnboarding->workflow->name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Started on {{ $myOnboarding->started_at->format('M d, Y') }}</p>
                    
                    <div class="w-full max-w-md bg-slate-100 rounded-full h-2 mb-1 dark:bg-slate-700">
                        <div class="bg-brand-600 h-2 rounded-full" style="width: {{ $myOnboarding->progressPercent() }}%"></div>
                    </div>
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $myOnboarding->progressPercent() }}% completed</p>
                </div>
                <div>
                    <a href="{{ route('onboarding.show', $myOnboarding) }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-slate-900 shadow hover:bg-slate-800 transition dark:bg-brand-600 dark:hover:bg-brand-700">
                        View My Tasks <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>
                </div>
            </div>
        @else
            <div class="py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="check-circle" class="h-6 w-6 text-emerald-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No active onboarding</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">You do not have any onboarding workflows assigned to you.</p>
            </div>
        @endif
    </div>

    <!-- Team Onboarding Tab -->
    @if($teamOnboardings->isNotEmpty() || auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
    <div x-show="activeTab === 'team_onboarding'" style="display: none;" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($teamOnboardings as $onboarding)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700/80 transition hover:shadow-md">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold dark:bg-brand-900 dark:text-brand-300">
                            {{ substr($onboarding->employee->first_name, 0, 1) }}{{ substr($onboarding->employee->last_name, 0, 1) }}
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $onboarding->employee->first_name }} {{ $onboarding->employee->last_name }}</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $onboarding->workflow->name }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold {{ $onboarding->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-400' }}">
                        {{ str_replace('_', ' ', ucfirst($onboarding->status)) }}
                    </span>
                </div>
                
                <div class="mb-4">
                    <div class="flex justify-between text-xs font-bold text-slate-500 mb-1 dark:text-slate-400">
                        <span>Progress</span>
                        <span>{{ $onboarding->progressPercent() }}%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5 dark:bg-slate-700">
                        <div class="bg-brand-600 h-1.5 rounded-full" style="width: {{ $onboarding->progressPercent() }}%"></div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between mt-auto pt-4 border-t border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Started {{ $onboarding->started_at->diffForHumans() }}</span>
                    <a href="{{ route('onboarding.show', $onboarding) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800 transition dark:text-brand-400">View Details</a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="users" class="h-6 w-6 text-slate-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No team onboardings</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">None of your team members are currently onboarding.</p>
            </div>
        @endforelse
    </div>
    @endif

    <!-- All Onboarding Tab (HR) -->
    @if(auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
    <div x-show="activeTab === 'all_onboarding'" style="display: none;" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Workflow</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Progress</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200 dark:bg-slate-800 dark:divide-slate-700">
                @forelse($allOnboardings as $onboarding)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">
                            {{ $onboarding->employee->first_name }} {{ $onboarding->employee->last_name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            {{ $onboarding->workflow->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-slate-100 rounded-full h-1.5 dark:bg-slate-700">
                                    <div class="bg-brand-600 h-1.5 rounded-full" style="width: {{ $onboarding->progressPercent() }}%"></div>
                                </div>
                                <span class="text-xs">{{ $onboarding->progressPercent() }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold {{ $onboarding->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-brand-100 text-brand-800 dark:bg-brand-500/20 dark:text-brand-400' }}">
                                {{ str_replace('_', ' ', ucfirst($onboarding->status)) }}
                            </span>
                            @if($onboarding->isOverdue())
                                <span class="inline-flex items-center rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800 dark:bg-rose-500/20 dark:text-rose-400 ml-1">Overdue</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('onboarding.show', $onboarding) }}" class="text-brand-600 hover:text-brand-900 dark:text-brand-400">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No onboardings found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($allOnboardings->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $allOnboardings->links() }}
            </div>
        @endif
    </div>
    @endif
    
    <!-- Start Onboarding Modal -->
    @if(auth()->user()->hasRole('hr_admin') || auth()->user()->hasRole('super_admin'))
    <div x-data="{ open: false }" @open-modal.window="if ($event.detail === 'start-onboarding') open = true" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-show="open" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" x-show="open"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-slate-800 border border-slate-200 dark:border-slate-700" @click.away="open = false">
                    <form action="{{ route('onboarding.start') }}" method="POST">
                        @csrf
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 dark:bg-slate-800">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-lg font-bold leading-6 text-slate-900 dark:text-white" id="modal-title">Start Onboarding Workflow</h3>
                                    <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                        <p>Assign a workflow to an employee. Tasks will be automatically generated and assigned based on the template definitions.</p>
                                    </div>
                                    
                                    <div class="mt-6 space-y-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Employee</label>
                                            <select name="user_id" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                @foreach($availableUsers as $u)
                                                    <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Workflow</label>
                                            <select name="workflow_id" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                @foreach($activeWorkflows as $w)
                                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700/60">
                            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-brand-600 px-3 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 sm:ml-3 sm:w-auto">Start</button>
                            <button type="button" @click="open = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
