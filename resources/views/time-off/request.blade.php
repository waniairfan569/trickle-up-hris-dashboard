@extends('layouts.hr-app')

@section('title', 'Request Time Off')
@section('breadcrumb', 'Request Time Off')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm dark:bg-slate-800 dark:border-slate-700 p-8">
        
        <h2 class="text-xl font-extrabold text-slate-900 dark:text-white mb-6">Submit Time Off Request</h2>

        @if($errors->any())
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 mb-6 dark:bg-rose-500/10 dark:border-rose-500/20">
                <div class="flex">
                    <i data-lucide="alert-circle" class="h-5 w-5 text-rose-400"></i>
                    <div class="ml-3 text-sm font-medium text-rose-800 dark:text-rose-300">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <div x-data="timeOffForm()">
            <!-- Form: Switches action based on whether on behalf is checked -->
            <form :action="onBehalf ? '{{ route('time-off.on-behalf') }}' : '{{ route('time-off.store') }}'" method="POST" class="space-y-6">
                @csrf

                @if(auth()->user()->isManager() || auth()->user()->isAdmin())
                <div class="flex items-center space-x-3 bg-slate-50 p-4 rounded-xl border border-slate-200 dark:bg-slate-900/50 dark:border-slate-700">
                    <input type="checkbox" id="onBehalf" x-model="onBehalf" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <label for="onBehalf" class="text-sm font-bold text-slate-700 dark:text-slate-300">Request on behalf of an employee</label>
                </div>

                <div x-show="onBehalf" x-cloak class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Select Employee</label>
                    <select name="user_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white" :required="onBehalf">
                        <option value="">-- Select Direct Report --</option>
                        @foreach($directReports as $report)
                            <option value="{{ $report->id }}">{{ $report->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Leave Policy</label>
                        <select name="policy_id" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white" required>
                            <option value="">-- Select Policy --</option>
                            @foreach($policies as $policy)
                                <option value="{{ $policy->id }}">{{ $policy->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Start Date</label>
                        <input type="date" name="start_date" x-model="startDate" @change="calculateDays" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">End Date</label>
                        <input type="date" name="end_date" x-model="endDate" @change="calculateDays" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white" required>
                    </div>
                </div>

                <!-- Half Day Option -->
                <div class="flex items-center space-x-3 bg-slate-50 p-4 rounded-xl border border-slate-200 dark:bg-slate-900/50 dark:border-slate-700">
                    <input type="checkbox" id="halfDay" name="is_half_day" x-model="halfDay" @change="calculateDays" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <label for="halfDay" class="text-sm font-bold text-slate-700 dark:text-slate-300">Half Day Leave</label>
                    <span class="text-xs text-slate-400">(Request only half a working day)</span>
                </div>

                <!-- Half Day Period Selector -->
                <div x-show="halfDay" x-cloak class="space-y-2">
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">Half Day Period</label>
                    <select name="half_day_period" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">
                        <option value="morning">Morning (First Half)</option>
                        <option value="afternoon">Afternoon (Second Half)</option>
                    </select>
                </div>

                <div class="rounded-xl bg-brand-50 p-4 border border-brand-100 flex items-center justify-between dark:bg-brand-500/10 dark:border-brand-500/20">
                    <span class="text-sm font-bold text-brand-800 dark:text-brand-300">Estimated Working Days:</span>
                    <span class="text-2xl font-extrabold text-brand-900 dark:text-brand-400" x-text="daysCount">0</span>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 dark:text-slate-300">
                        Reason <span x-show="!onBehalf" class="text-rose-500">*</span><span x-show="onBehalf" class="font-normal text-slate-400">(Optional)</span>
                    </label>
                    <textarea name="reason" rows="3" :required="!onBehalf" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-700 dark:text-white">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <a href="{{ route('time-off.index') }}" class="btn-outline">Cancel</a>
                    <button type="submit" class="btn-brand">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function timeOffForm() {
        return {
            onBehalf: false,
            halfDay: false,
            startDate: '',
            endDate: '',
            daysCount: 0,
            
            calculateDays() {
                if (!this.startDate || !this.endDate) return;
                
                const start = new Date(this.startDate);
                const end = new Date(this.endDate);
                
                if (end < start) {
                    this.daysCount = 0;
                    return;
                }
                
                let count = 0;
                let current = new Date(start);
                
                while (current <= end) {
                    const dayOfWeek = current.getDay();
                    // 0 is Sunday, 6 is Saturday
                    if (dayOfWeek !== 0 && dayOfWeek !== 6) {
                        count++;
                    }
                    current.setDate(current.getDate() + 1);
                }
                
                // If half day is checked, use 0.5 instead of the calculated count
                if (this.halfDay) {
                    this.daysCount = 0.5;
                } else {
                    this.daysCount = count;
                }
            }
        }
    }
</script>
@endsection
