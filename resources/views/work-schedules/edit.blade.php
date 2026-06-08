@extends('layouts.hr-app')

@section('title', 'Edit Work Schedule')
@section('breadcrumb', 'Edit Schedule')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                Edit <span class="text-brand-600">{{ $workSchedule->name }}</span>
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Update days and hours. This will affect future time-off requests.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('work-schedules.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
                Back to List
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-400">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('work-schedules.update', $workSchedule) }}" method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        @csrf
        @method('PUT')
        
        <div class="p-8 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Schedule Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $workSchedule->name) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('description', $workSchedule->description) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 dark:text-slate-300">Working Days <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-4">
                        @php 
                            $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; 
                            $currentDays = old('working_days', $workSchedule->working_days ?? []);
                        @endphp
                        @foreach($days as $day)
                            <label class="inline-flex items-center p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition dark:border-slate-700 dark:hover:bg-slate-700/50">
                                <input type="checkbox" name="working_days[]" value="{{ $day }}" 
                                       class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900"
                                       {{ in_array($day, $currentDays) ? 'checked' : '' }}>
                                <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Start Time <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" value="{{ old('start_time', \Carbon\Carbon::parse($workSchedule->start_time)->format('H:i')) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">End Time <span class="text-red-500">*</span></label>
                    <input type="time" name="end_time" value="{{ old('end_time', \Carbon\Carbon::parse($workSchedule->end_time)->format('H:i')) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Hours Per Day <span class="text-red-500">*</span></label>
                    <input type="number" step="0.5" min="1" max="24" name="hours_per_day" value="{{ old('hours_per_day', $workSchedule->hours_per_day) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Days Per Week <span class="text-red-500">*</span></label>
                    <input type="number" min="1" max="7" name="days_per_week" value="{{ old('days_per_week', $workSchedule->days_per_week) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100 dark:border-slate-700/60 space-y-4">
                <label class="flex items-start">
                    <div class="flex items-center h-5">
                        <input type="checkbox" name="is_default" value="1" 
                               class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900"
                               {{ old('is_default', $workSchedule->is_default) ? 'checked' : '' }}>
                    </div>
                    <div class="ml-3 text-sm">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Set as default schedule</span>
                    </div>
                </label>
                
                <label class="flex items-start">
                    <div class="flex items-center h-5">
                        <input type="checkbox" name="is_active" value="1" 
                               class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500"
                               {{ old('is_active', $workSchedule->is_active) ? 'checked' : '' }}>
                    </div>
                    <div class="ml-3 text-sm">
                        <span class="font-bold text-slate-700 dark:text-slate-300">Schedule is Active</span>
                    </div>
                </label>
            </div>
        </div>
        
        <div class="bg-slate-50 px-8 py-5 border-t border-slate-100 dark:bg-slate-800/50 dark:border-slate-700/60 flex items-center justify-between">
            <button type="button" onclick="document.getElementById('delete-form').submit();" class="text-sm font-bold text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition">
                Delete Schedule
            </button>
            <div class="flex gap-3">
                <a href="{{ route('work-schedules.index') }}" class="inline-flex justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                    Cancel
                </a>
                <button type="submit" class="inline-flex justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                    Save Changes
                </button>
            </div>
        </div>
    </form>
    
    <form id="delete-form" action="{{ route('work-schedules.destroy', $workSchedule) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection
