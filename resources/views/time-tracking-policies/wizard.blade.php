@extends('layouts.hr-app')

@section('title', $policy ? 'Edit time tracking policy' : 'Add time tracking policy')
@section('breadcrumb', 'Time Tracking')

@section('content')
<style>[x-cloak]{display:none!important}</style>

@php
    $entityOpts = $entities->map(fn ($e) => ['id' => (string) $e->id, 'name' => $e->name])->values();
    $deptOpts = $departments->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name])->values();
@endphp
<script>
    window.__ttExisting = @json($existing);
    window.__ttEntities = @json($entityOpts);
    window.__ttDepartments = @json($deptOpts);
</script>

@php
    $steps = [
        1 => ['Policy details', 'Name & scope'],
        2 => ['Submission method', 'How hours are logged'],
        3 => ['Geofencing', 'Where employees clock in'],
        4 => ['Submission frequency', 'Reminders'],
    ];
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

<form method="POST" action="{{ $policy ? route('time-tracking-policies.update', $policy) : route('time-tracking-policies.store') }}"
      x-data="ttWizard()" class="max-w-5xl mx-auto pb-10">
    @csrf
    @if($policy) @method('PUT') @endif

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('time-tracking-policies.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700" title="Cancel"><i data-lucide="x" class="h-5 w-5"></i></a>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $policy ? 'Edit time tracking policy' : 'Add time tracking policy' }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="`Step ${step} of 4`"></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="prev()" x-show="step > 1" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="arrow-left" class="h-4 w-4"></i> Previous</button>
            <button type="button" @click="next()" x-show="step < 4" :disabled="!canAdvance()" :class="!canAdvance() && 'opacity-50 cursor-not-allowed'" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">Next <i data-lucide="arrow-right" class="h-4 w-4"></i></button>
            <button type="submit" x-show="step === 4" :disabled="name.trim() === ''" :class="name.trim() === '' && 'opacity-50 cursor-not-allowed'" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 mb-6 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[230px_1fr] gap-6">
        <!-- Step sidebar -->
        <aside class="space-y-1.5">
            @foreach($steps as $i => $meta)
                <button type="button" @click="go({{ $i }})" class="w-full text-left flex items-start gap-3 rounded-xl px-3.5 py-3 transition" :class="step === {{ $i }} ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-[11px] font-bold" :class="step === {{ $i }} ? 'bg-brand-600 text-slate-900' : (step > {{ $i }} ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-700')">
                        <template x-if="step > {{ $i }}"><i data-lucide="check" class="h-3.5 w-3.5"></i></template>
                        <template x-if="step <= {{ $i }}"><span>{{ $i }}</span></template>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold leading-tight" :class="step === {{ $i }} ? 'text-brand-700 dark:text-brand-400' : 'text-slate-700 dark:text-slate-200'">{{ $meta[0] }}</span>
                        <span class="block text-[11px] text-slate-400 leading-tight mt-0.5">{{ $meta[1] }}</span>
                    </span>
                </button>
            @endforeach
        </aside>

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 min-h-[420px]">
            {{-- STEP 1 --}}
            <div x-show="step === 1" class="space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Policy name <span class="text-rose-500">*</span></label>
                        <span class="text-[11px]" :class="name.length > 70 ? 'text-rose-500' : 'text-slate-400'" x-text="name.length + '/70'"></span>
                    </div>
                    <input type="text" name="name" x-model="name" maxlength="70" placeholder="e.g. Clock in / Clock out" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Description <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                        <span class="text-[11px]" :class="description.length > 140 ? 'text-rose-500' : 'text-slate-400'" x-text="description.length + '/140'"></span>
                    </div>
                    <textarea name="description" x-model="description" maxlength="140" rows="2" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    @include('time-tracking-policies._multiselect', ['label' => 'Entities & locations', 'hint' => 'Leave empty to apply to all.', 'model' => 'entities', 'options' => 'entityOptions', 'name' => 'entities'])
                    @include('time-tracking-policies._multiselect', ['label' => 'Departments', 'hint' => 'Leave empty to apply to all.', 'model' => 'departments', 'options' => 'departmentOptions', 'name' => 'departments'])
                </div>
            </div>

            {{-- STEP 2 --}}
            <div x-show="step === 2" x-cloak class="space-y-6">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Timesheet input method</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Choose how employees log their work hours.</p>
                </div>
                <label class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer" :class="submissionMethod === 'clock_in_out' ? 'border-brand-400 bg-brand-50/40 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                    <input type="radio" name="submission_method" value="clock_in_out" x-model="submissionMethod" class="mt-1 text-brand-600">
                    <span>
                        <span class="block text-sm font-bold text-slate-800 dark:text-white">Clock in / Clock out</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Employees log work automatically with a real-time clock in / clock out timer.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer" :class="submissionMethod === 'manual' ? 'border-brand-400 bg-brand-50/40 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                    <input type="radio" name="submission_method" value="manual" x-model="submissionMethod" class="mt-1 text-brand-600">
                    <span>
                        <span class="block text-sm font-bold text-slate-800 dark:text-white">Submit timesheet entry</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Employees manually enter their start and end time after the fact.</span>
                    </span>
                </label>
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4 dark:border-slate-600">
                    <div>
                        <span class="block text-sm font-bold text-slate-800 dark:text-white">Allow timesheet editing</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">Let employees edit their submitted time entries.</span>
                    </div>
                    <input type="hidden" name="allow_timesheet_editing" :value="allowEditing ? 1 : 0">
                    <button type="button" @click="allowEditing = !allowEditing" class="relative inline-flex h-6 w-11 items-center rounded-full transition" :class="allowEditing ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-600'">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="allowEditing ? 'translate-x-6' : 'translate-x-1'"></span>
                    </button>
                </div>
            </div>

            {{-- STEP 3 --}}
            <div x-show="step === 3" x-cloak class="space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Geofencing</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Restrict where employees can clock in / out, per site.</p>
                    </div>
                    <input type="hidden" name="geofencing_enabled" :value="geofencingEnabled ? 1 : 0">
                    <button type="button" @click="geofencingEnabled = !geofencingEnabled" class="relative inline-flex h-6 w-11 items-center rounded-full transition" :class="geofencingEnabled ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-600'">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="geofencingEnabled ? 'translate-x-6' : 'translate-x-1'"></span>
                    </button>
                </div>
                <div x-show="geofencingEnabled" x-cloak class="space-y-2">
                    @forelse($sites as $site)
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 cursor-pointer dark:border-slate-600">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-800 dark:text-white truncate">{{ $site->name }}@if($site->entity) <span class="text-slate-400 font-normal">· {{ $site->entity->name }}</span>@endif</span>
                                <span class="block text-xs text-slate-400 truncate">{{ $site->address ?: 'No address' }}@if($site->radius_meters) · {{ $site->radius_meters }}m radius @endif</span>
                            </span>
                            <input type="checkbox" name="sites[]" value="{{ $site->id }}" x-model="sites" class="rounded border-slate-300 text-brand-600 shrink-0">
                        </label>
                    @empty
                        <p class="text-xs text-slate-400">No sites configured. Add Office Locations first.</p>
                    @endforelse
                </div>
                <p x-show="!geofencingEnabled" x-cloak class="text-xs text-slate-400">Geofencing is off — employees can clock in from anywhere.</p>
            </div>

            {{-- STEP 4 --}}
            <div x-show="step === 4" x-cloak class="space-y-6">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-600">
                    <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Submission frequency</span>
                    <p class="text-sm font-semibold text-slate-800 dark:text-white" x-text="submissionMethod === 'clock_in_out' ? 'Real time' : 'Manual timesheet'"></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" x-text="submissionMethod === 'clock_in_out' ? 'Employees log their work in real time using the clock in / clock out timer.' : 'Employees submit their hours by entering start and end times.'"></p>
                </div>

                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-3">Reminders frequency</h2>
                    <label class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer mb-2" :class="remindersFrequency === 'work_schedule' ? 'border-brand-400 bg-brand-50/40 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                        <input type="radio" name="reminders_frequency" value="work_schedule" x-model="remindersFrequency" class="mt-1 text-brand-600">
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Based on work schedule</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Remind at the start and end of each work schedule. Employees with no schedule won't be reminded.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer" :class="remindersFrequency === 'custom' ? 'border-brand-400 bg-brand-50/40 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                        <input type="radio" name="reminders_frequency" value="custom" x-model="remindersFrequency" class="mt-1 text-brand-600">
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Custom reminders</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Send reminders on specific days and times.</span>
                        </span>
                    </label>

                    <div x-show="remindersFrequency === 'custom'" x-cloak class="mt-3 space-y-2">
                        <template x-for="(r, i) in reminders" :key="i">
                            <div class="flex items-center gap-2">
                                <select x-model="r.day" class="rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    @foreach($days as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                                </select>
                                <input type="time" x-model="r.time" class="rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <button type="button" @click="reminders.splice(i, 1)" class="text-slate-400 hover:text-rose-500"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                            </div>
                        </template>
                        <button type="button" @click="reminders.push({ day: 'Mon', time: '09:00' })" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-600 hover:text-brand-700"><i data-lucide="plus" class="h-4 w-4"></i> Add reminder</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden inputs for custom reminders -->
    <div class="hidden" aria-hidden="true">
        <template x-for="(r, i) in (remindersFrequency === 'custom' ? reminders : [])" :key="'r' + i">
            <span>
                <input type="hidden" :name="`reminders[${i}][day]`" :value="r.day">
                <input type="hidden" :name="`reminders[${i}][time]`" :value="r.time">
            </span>
        </template>
    </div>
</form>

<script>
    function ttWizard() {
        const ex = window.__ttExisting || null;
        return {
            step: 1,
            name: ex?.name || '',
            description: ex?.description || '',
            entities: ex?.entities ? [...ex.entities] : [],
            departments: ex?.departments ? [...ex.departments] : [],
            submissionMethod: ex?.submission_method || 'clock_in_out',
            allowEditing: ex ? !!ex.allow_timesheet_editing : false,
            geofencingEnabled: ex ? !!ex.geofencing_enabled : false,
            sites: ex?.sites ? [...ex.sites] : [],
            remindersFrequency: ex?.reminders_frequency || 'work_schedule',
            reminders: ex?.reminders ? ex.reminders.map(r => ({ day: r.day, time: r.time })) : [],
            entityOptions: window.__ttEntities || [],
            departmentOptions: window.__ttDepartments || [],

            optName(options, id) { const o = options.find(o => String(o.id) === String(id)); return o ? o.name : id; },
            removeId(arr, id) { return arr.filter(x => String(x) !== String(id)); },

            go(s) { this.step = s; this.$nextTick(() => window.lucide && lucide.createIcons()); },
            next() { if (this.step < 4 && this.canAdvance()) this.go(this.step + 1); },
            prev() { if (this.step > 1) this.go(this.step - 1); },
            canAdvance() { return this.step !== 1 || this.name.trim() !== ''; },
        };
    }
</script>
@endsection
