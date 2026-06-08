@extends('layouts.hr-app')

@section('title', 'Holiday Calendars')
@section('breadcrumb', 'Holiday Calendars')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Holiday Calendars</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage public holidays and company days off globally or by region.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <button onclick="document.getElementById('create-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                New Calendar
            </button>
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
        @forelse($calendars as $calendar)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700/80 relative transition hover:shadow-md">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="h-10 w-10 rounded-full bg-indigo-50 flex items-center justify-center dark:bg-indigo-500/10">
                            <i data-lucide="calendar-days" class="h-5 w-5 text-indigo-600 dark:text-indigo-400"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                <a href="{{ route('holiday-calendars.show', $calendar) }}" class="hover:text-brand-600 transition">{{ $calendar->name }}</a>
                            </h3>
                            @if($calendar->country_code || $calendar->year)
                                <p class="text-xs text-slate-500 font-medium dark:text-slate-400">
                                    {{ $calendar->country_code }} {{ $calendar->country_code && $calendar->year ? '|' : '' }} {{ $calendar->year }}
                                </p>
                            @endif
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-between">
                        <div class="bg-slate-50 rounded-xl p-3 flex-1 mr-2 dark:bg-slate-900">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Holidays</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $calendar->holidays_count }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-3 flex-1 ml-2 dark:bg-slate-900">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Employees</p>
                            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $calendar->users_count }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between dark:border-slate-700/60">
                    <div>
                        @if(!$calendar->is_active)
                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">Inactive</span>
                        @endif
                    </div>
                    
                    <a href="{{ route('holiday-calendars.show', $calendar) }}" class="text-sm font-bold text-brand-600 hover:text-brand-800 transition dark:text-brand-400">
                        Manage &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
                <div class="mx-auto h-12 w-12 rounded-full bg-slate-50 flex items-center justify-center mb-3 dark:bg-slate-900">
                    <i data-lucide="calendar-off" class="h-6 w-6 text-slate-400"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No holiday calendars</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a calendar to track public holidays.</p>
                <div class="mt-6">
                    <button onclick="document.getElementById('create-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700 transition">
                        <i data-lucide="plus" class="h-4 w-4"></i> Create Calendar
                    </button>
                </div>
            </div>
        @endforelse
    </div>
</div>

<!-- Create Modal -->
<div id="create-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-slate-900/75 backdrop-blur-sm" onclick="document.getElementById('create-modal').classList.add('hidden')"></div>

        <div class="relative inline-block w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white rounded-2xl shadow-xl dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Create Holiday Calendar</h3>
                <button onclick="document.getElementById('create-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-500 focus:outline-none">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            
            <form action="{{ route('holiday-calendars.store') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required placeholder="e.g. UK Public Holidays 2025" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Country Code</label>
                            <input type="text" name="country_code" placeholder="e.g. GB" maxlength="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Year</label>
                            <input type="number" name="year" placeholder="e.g. 2025" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('create-modal').classList.add('hidden')" class="rounded-xl px-4 py-2 bg-white border border-slate-300 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300">Cancel</button>
                    <button type="submit" class="rounded-xl px-4 py-2 bg-brand-600 text-sm font-bold text-slate-900 shadow-sm hover:bg-brand-700">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
