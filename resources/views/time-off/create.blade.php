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
                    <input type="date" name="start_date" x-model="startDate" @change="calculateDays" required min="{{ date('Y-m-d') }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">End Date <span class="text-red-500">*</span></label>
                    <input type="date" name="end_date" x-model="endDate" @change="calculateDays" required min="{{ date('Y-m-d') }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>

            <!-- Half Day Toggle -->
            <div x-show="startDate === endDate && startDate !== ''" style="display: none;" class="pt-4 pb-2">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_half_day" value="1" x-model="isHalfDay" @change="calculateDays" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 dark:peer-focus:ring-brand-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
                    <span class="ml-3 text-sm font-bold text-slate-700 dark:text-slate-300">Request Half Day</span>
                </label>
                
                <div x-show="isHalfDay" class="mt-4 flex gap-4 ml-14">
                    <label class="inline-flex items-center">
                        <input type="radio" name="half_day_period" value="morning" class="text-brand-600 border-slate-300 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600" checked>
                        <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Morning</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="half_day_period" value="afternoon" class="text-brand-600 border-slate-300 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600">
                        <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">Afternoon</span>
                    </label>
                </div>
            </div>

            <!-- Reason -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Reason / Note (Optional)</label>
                <textarea name="reason" rows="3" placeholder="Add a note for your manager..." class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
            </div>
            
            <input type="hidden" name="days_requested" x-model="calculatedDays">

        </div>
        
        <div class="px-8 py-5 bg-slate-50 border-t border-slate-200 flex items-center justify-between dark:bg-slate-900/50 dark:border-slate-700/60">
            <div class="text-sm">
                <span class="text-slate-500 dark:text-slate-400">Requesting: </span>
                <span class="font-extrabold text-lg text-slate-900 dark:text-white" x-text="calculatedDays">0</span>
                <span class="text-slate-500 dark:text-slate-400"> working days</span>
            </div>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-brand-600 px-6 py-3 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                Submit Request
            </button>
        </div>
    </form>
</div>

<script>
    function timeOffForm() {
        return {
            selectedPolicy: {{ old('policy_id', $myPolicies->first()->id ?? 'null') }},
            startDate: '{{ old('start_date') }}',
            endDate: '{{ old('end_date') }}',
            isHalfDay: {{ old('is_half_day', 0) ? 'true' : 'false' }},
            calculatedDays: 0,
            
            init() {
                this.$watch('startDate', value => {
                    if(!this.endDate || this.endDate < value) this.endDate = value;
                    if(this.startDate !== this.endDate) this.isHalfDay = false;
                    this.calculateDays();
                });
                this.$watch('endDate', value => {
                    if(this.startDate && value < this.startDate) this.startDate = value;
                    if(this.startDate !== this.endDate) this.isHalfDay = false;
                    this.calculateDays();
                });
                this.calculateDays();
            },
            
            calculateDays() {
                if(!this.startDate || !this.endDate) {
                    this.calculatedDays = 0;
                    return;
                }
                
                if (this.isHalfDay && this.startDate === this.endDate) {
                    this.calculatedDays = 0.5;
                    return;
                }

                // Basic frontend calculation (ignoring holidays for now, server handles exact)
                // We count weekdays
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
