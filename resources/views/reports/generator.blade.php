@extends('layouts.hr-app')

@section('title', 'Report Generator')
@section('breadcrumb', 'Report Generator')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="reportGenerator(@js($employees))">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="file-bar-chart-2" class="h-6 w-6 text-brand-500"></i> Report Generator
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Generate attendance &amp; leave reports for any employee or all employees.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('reports.generate.submit') }}" x-ref="form">
        @csrf
        <input type="hidden" name="report_scope" :value="scope">
        <input type="hidden" name="report_type" :value="type">
        <input type="hidden" name="output" x-ref="output" value="pdf">
        <input type="hidden" name="employee_id" :value="employeeId">

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6 dark:bg-slate-800 dark:border-slate-700">

            {{-- ROW 1 — Who --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Who</label>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" @click="scope='single'"
                            :class="scope==='single' ? 'border-brand-500 bg-brand-50 text-slate-900 dark:bg-brand-500/10 dark:text-white' : 'border-slate-200 text-slate-500 dark:border-slate-600'"
                            class="flex items-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-bold transition">
                        <i data-lucide="user" class="h-4 w-4"></i> Single Employee
                    </button>
                    <button type="button" @click="scope='all'"
                            :class="scope==='all' ? 'border-brand-500 bg-brand-50 text-slate-900 dark:bg-brand-500/10 dark:text-white' : 'border-slate-200 text-slate-500 dark:border-slate-600'"
                            class="flex items-center gap-2 rounded-xl border-2 px-4 py-3 text-sm font-bold transition">
                        <i data-lucide="users" class="h-4 w-4"></i> All Employees
                    </button>
                </div>

                {{-- searchable employee dropdown --}}
                <div x-show="scope==='single'" x-cloak class="mt-3 relative" @click.away="empOpen=false">
                    <div @click="empOpen=!empOpen"
                         class="flex items-center justify-between gap-2 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm cursor-pointer dark:border-slate-600 dark:bg-slate-900">
                        <template x-if="selectedEmployee">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="h-7 w-7 shrink-0 grid place-items-center rounded-full bg-brand-100 text-brand-700 text-[10px] font-bold dark:bg-brand-500/20 dark:text-brand-400" x-text="selectedEmployee.initials"></span>
                                <span class="truncate text-slate-800 dark:text-white" x-text="selectedEmployee.name + ' · ' + selectedEmployee.job_title"></span>
                            </span>
                        </template>
                        <span x-show="!selectedEmployee" class="text-slate-400">Select an employee…</span>
                        <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 shrink-0"></i>
                    </div>
                    <div x-show="empOpen" x-cloak class="absolute z-30 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-xl max-h-72 overflow-y-auto dark:bg-slate-800 dark:border-slate-700">
                        <div class="p-2 sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700">
                            <input type="text" x-model="employeeSearch" placeholder="Search name or department…" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        <template x-for="e in filteredEmployees" :key="e.id">
                            <button type="button" @click="employeeId=e.id; empOpen=false"
                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <span class="h-8 w-8 shrink-0 grid place-items-center rounded-full bg-brand-100 text-brand-700 text-[11px] font-bold dark:bg-brand-500/20 dark:text-brand-400" x-text="e.initials"></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-800 dark:text-white truncate" x-text="e.name"></span>
                                    <span class="block text-[11px] text-slate-400 truncate" x-text="e.job_title + ' · ' + e.department"></span>
                                </span>
                            </button>
                        </template>
                        <div x-show="filteredEmployees.length===0" class="px-3 py-4 text-center text-xs text-slate-400">No match.</div>
                    </div>
                </div>
            </div>

            {{-- ROW 2 — Period type --}}
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Period</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <template x-for="pt in periodTypes" :key="pt.key">
                        <button type="button" @click="type=pt.key"
                                :class="type===pt.key ? 'border-brand-500 bg-brand-50 text-slate-900 dark:bg-brand-500/10 dark:text-white' : 'border-slate-200 text-slate-500 dark:border-slate-600'"
                                class="rounded-xl border-2 px-3 py-2.5 text-xs font-bold transition" x-text="pt.label"></button>
                    </template>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-3">
                    {{-- monthly --}}
                    <div x-show="type==='monthly'">
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Month</label>
                        <select name="month" x-model="month" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <template x-for="(mn, i) in months" :key="i"><option :value="i+1" x-text="mn"></option></template>
                        </select>
                    </div>
                    {{-- mid-year --}}
                    <div x-show="type==='mid_year'">
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Half</label>
                        <select name="half" x-model="half" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="first">First half (Jan – Jun)</option>
                            <option value="second">Second half (Jul – Dec)</option>
                        </select>
                    </div>
                    {{-- year (monthly/mid_year/yearly) --}}
                    <div x-show="type!=='custom'">
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Year</label>
                        <input type="number" name="year" x-model="year" min="2000" max="2100" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    {{-- custom --}}
                    <div x-show="type==='custom'">
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">From</label>
                        <input type="date" name="date_from" x-model="dateFrom" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div x-show="type==='custom'">
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">To</label>
                        <input type="date" name="date_to" x-model="dateTo" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
            </div>

            {{-- ROW 3 — Live preview card --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900/40">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-3">Will generate</p>
                <div class="flex items-center gap-2.5 text-sm">
                    <i data-lucide="user" class="h-4 w-4 text-brand-500"></i>
                    <span class="font-bold text-slate-800 dark:text-white" x-text="scope==='all' ? 'All employees (' + employees.length + ')' : (selectedEmployee ? selectedEmployee.name : 'No employee selected')"></span>
                </div>
                <div class="flex items-center gap-2.5 text-xs text-slate-500 dark:text-slate-400 mt-1 ml-6.5" x-show="scope==='single' && selectedEmployee" x-text="selectedEmployee ? (selectedEmployee.job_title + ' · ' + selectedEmployee.department) : ''"></div>
                <div class="flex items-center gap-2.5 text-sm mt-3">
                    <i data-lucide="calendar" class="h-4 w-4 text-brand-500"></i>
                    <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="periodLabel"></span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span>✓ Attendance summary</span>
                    <span>✓ Daily breakdown</span>
                    <span>✓ Leave summary &amp; balances</span>
                    <span>✓ Performance score</span>
                </div>
            </div>

            {{-- ROW 4 — Actions --}}
            <div class="flex flex-wrap items-center justify-end gap-3 pt-1">
                <button type="button" @click="submit('preview')" :disabled="!valid" :class="!valid ? 'opacity-50 cursor-not-allowed' : ''"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="eye" class="h-4 w-4"></i>
                    <span x-text="scope==='all' ? 'Preview (combined)' : 'Preview'"></span>
                </button>
                <button type="button" @click="submit('pdf')" :disabled="!valid" :class="!valid ? 'opacity-50 cursor-not-allowed' : ''"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-400 transition">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    <span x-text="scope==='all' ? 'Download all (ZIP)' : 'Download PDF'"></span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function reportGenerator(employees) {
        const now = new Date();
        return {
            employees: employees,
            months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
            periodTypes: [
                { key: 'monthly',  label: '📅 Monthly' },
                { key: 'mid_year', label: '📆 Mid-Year' },
                { key: 'yearly',   label: '🗓 Full Year' },
                { key: 'custom',   label: '⚙ Custom' },
            ],
            scope: 'single',
            type: 'monthly',
            employeeId: '',
            employeeSearch: '',
            empOpen: false,
            month: now.getMonth() + 1,
            year: now.getFullYear(),
            half: 'first',
            dateFrom: '',
            dateTo: '',
            get selectedEmployee() { return this.employees.find(e => e.id == this.employeeId) || null; },
            get filteredEmployees() {
                const q = this.employeeSearch.toLowerCase().trim();
                if (!q) return this.employees;
                return this.employees.filter(e => (e.name + ' ' + e.department + ' ' + e.job_title).toLowerCase().includes(q));
            },
            get periodLabel() {
                if (this.type === 'monthly')  return this.months[this.month - 1] + ' ' + this.year;
                if (this.type === 'yearly')   return 'Full Year ' + this.year;
                if (this.type === 'mid_year') return (this.half === 'first' ? 'Jan – Jun ' : 'Jul – Dec ') + this.year;
                return (this.dateFrom || '—') + '  →  ' + (this.dateTo || '—');
            },
            get valid() {
                if (this.scope === 'single' && !this.employeeId) return false;
                if (this.type === 'custom' && (!this.dateFrom || !this.dateTo)) return false;
                return true;
            },
            submit(output) {
                if (!this.valid) return;
                this.$refs.output.value = output;
                this.$refs.form.target = (output === 'preview') ? '_blank' : '_self';
                this.$refs.form.submit();
            },
        };
    }
</script>
@endsection
