@extends('layouts.hr-app')

@section('title', 'Request Time-Off')
@section('breadcrumb', 'Request Time-Off')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="timeOffForm()">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Request Time-Off</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Submit a new leave request.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('time-off.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
                Back to Dashboard
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('time-off.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        @csrf
        <div class="p-8 space-y-6">
            
            <!-- Policy Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Select Policy <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($myPolicies as $policy)
                        @php $balance = $balances[$policy->id]; @endphp
                        <label class="relative flex cursor-pointer rounded-xl border p-4 shadow-sm focus:outline-none transition"
                               :class="selectedPolicy == {{ $policy->id }} ? 'border-brand-500 ring-2 ring-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                            <input type="radio" name="policy_id" value="{{ $policy->id }}" x-model="selectedPolicy" class="sr-only">
                            <span class="flex flex-1">
                                <span class="flex flex-col">
                                    <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ $policy->name }}</span>
                                    <span class="mt-1 flex items-center text-xs text-slate-500 dark:text-slate-400">
                                        {{ (float) $balance->remaining }} days remaining
                                    </span>
                                </span>
                            </span>
                            <i data-lucide="check-circle" class="h-5 w-5 text-brand-600" x-show="selectedPolicy == {{ $policy->id }}"></i>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Start Date <span class="text-red-500">*</span></label>
                    <input type="date" name="start_date" x-model="startDate" @change="calculateDays" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">End Date <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" x-model="endDate" @change="calculateDays" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>

            <!-- Duration type (single date only: full day / half day / hourly) -->
            <div x-show="startDate === endDate && startDate !== ''" style="display: none;" class="pt-4 pb-2">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Duration</label>
                <div class="inline-flex rounded-xl border border-slate-300 p-1 dark:border-slate-600">
                    <template x-for="opt in [{k:'full_day',l:'Full day'},{k:'half_day',l:'Half day'},{k:'hourly',l:'Hourly'}]" :key="opt.k">
                        <button type="button" @click="durationType = opt.k; calculateDays()"
                                :class="durationType === opt.k ? 'bg-brand-600 text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                                class="px-4 py-1.5 text-sm font-bold rounded-lg transition" x-text="opt.l"></button>
                    </template>
                </div>

                <!-- Half-day period -->
                <div x-show="durationType === 'half_day'" class="mt-4 flex gap-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="half_day_period" value="morning" x-model="halfPeriod" class="text-brand-600 border-slate-300 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600">
                        <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Morning</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="half_day_period" value="afternoon" x-model="halfPeriod" class="text-brand-600 border-slate-300 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600">
                        <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Afternoon</span>
                    </label>
                </div>

                <!-- Hourly time window -->
                <div x-show="durationType === 'hourly'" class="mt-4 grid grid-cols-2 gap-4 max-w-sm">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 dark:text-slate-300">Start time</label>
                        <input type="time" name="start_time" x-model="startTime" @input="calculateDays" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1 dark:text-slate-300">End time</label>
                        <input type="time" name="end_time" x-model="endTime" @input="calculateDays" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Reason -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Reason / Note <span class="text-rose-500">*</span></label>
                <textarea name="reason" rows="3" required placeholder="Add a note for your manager..." class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('reason') }}</textarea>
                @error('reason')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>
            
            <input type="hidden" name="duration_type" x-model="durationType">
            <input type="hidden" name="days_requested" x-model="calculatedDays">

        </div>

        <div class="px-8 py-5 bg-slate-50 border-t border-slate-200 flex items-center justify-between dark:bg-slate-900/50 dark:border-slate-700/60">
            <div class="text-sm">
                <span class="text-slate-500 dark:text-slate-400">Requesting: </span>
                <template x-if="durationType === 'hourly'">
                    <span>
                        <span class="font-extrabold text-lg text-slate-900 dark:text-white" x-text="hours">0</span>
                        <span class="text-slate-500 dark:text-slate-400"> hours</span>
                        <span class="text-xs text-slate-400" x-text="'(≈ ' + calculatedDays + ' day)'"></span>
                    </span>
                </template>
                <template x-if="durationType !== 'hourly'">
                    <span>
                        <span class="font-extrabold text-lg text-slate-900 dark:text-white" x-text="calculatedDays">0</span>
                        <span class="text-slate-500 dark:text-slate-400"> working day(s)</span>
                    </span>
                </template>
            </div>
            <button type="submit" class="btn-brand">
                Submit Request
            </button>
        </div>
    </form>
</div>

<script>
    function timeOffForm() {
        return {
            selectedPolicy: {{ old('policy_id', $myPolicies->first()->id ?? 'null') }},
            startDate: '{{ old('start_date', request('start_date')) }}',
            endDate: '{{ old('end_date', request('start_date')) }}',
            durationType: '{{ old('duration_type', 'full_day') }}',
            startTime: '{{ old('start_time') }}',
            endTime: '{{ old('end_time') }}',
            halfPeriod: '{{ old('half_day_period', 'morning') }}',
            hoursPerDay: {{ (float) \App\Models\TimeOffRequest::hoursPerDayFor(auth()->id()) }},
            hours: 0,
            calculatedDays: 0,

            init() {
                this.$watch('startDate', value => {
                    if(!this.endDate || this.endDate < value) this.endDate = value;
                    if(this.startDate !== this.endDate) this.durationType = 'full_day';
                    this.calculateDays();
                });
                this.$watch('endDate', value => {
                    if(this.startDate && value < this.startDate) this.startDate = value;
                    if(this.startDate !== this.endDate) this.durationType = 'full_day';
                    this.calculateDays();
                });
                this.calculateDays();
            },

            computeHours() {
                if(!this.startTime || !this.endTime) return 0;
                const [sh, sm] = this.startTime.split(':').map(Number);
                const [eh, em] = this.endTime.split(':').map(Number);
                let mins = (eh * 60 + em) - (sh * 60 + sm);
                return mins > 0 ? Math.round((mins / 60) * 100) / 100 : 0;
            },

            calculateDays() {
                this.hours = 0;
                if(!this.startDate || !this.endDate) {
                    this.calculatedDays = 0;
                    return;
                }

                const single = this.startDate === this.endDate;
                if(!single) this.durationType = 'full_day';

                if (single && this.durationType === 'half_day') {
                    this.calculatedDays = 0.5;
                    return;
                }

                if (single && this.durationType === 'hourly') {
                    this.hours = this.computeHours();
                    this.calculatedDays = this.hoursPerDay > 0
                        ? Math.round((this.hours / this.hoursPerDay) * 100) / 100
                        : 0;
                    return;
                }

                // Full day: count weekdays (server does the exact holiday-aware count)
                let start = new Date(this.startDate);
                let end = new Date(this.endDate);
                let count = 0;
                let cur = new Date(start);

                while (cur <= end) {
                    let dayOfWeek = cur.getDay();
                    if(dayOfWeek !== 0 && dayOfWeek !== 6) count++; // Not Sunday(0) or Saturday(6)
                    cur.setDate(cur.getDate() + 1);
                }

                this.calculatedDays = count;
            }
        }
    }
</script>
@endsection
