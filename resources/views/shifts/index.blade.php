@extends('layouts.hr-app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="shiftManager()">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Shift Management</h1>
            <p class="text-sm text-slate-500 mt-1">Define work shifts and set the default shift for new employees</p>
        </div>
        <button @click="openCreateForm()" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-4 rounded-lg shadow-sm transition">
            + Create New Shift
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
            {{ session('error') }}
        </div>
    @endif

    <!-- Default Shift Banner -->
    @if($defaultShift)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-8 flex items-start">
            <div class="bg-amber-100 p-2 rounded-lg mr-4">
                <i data-lucide="star" class="w-6 h-6 text-amber-600"></i>
            </div>
            <div>
                <h3 class="text-amber-800 font-bold text-lg flex items-center">
                    Default shift: {{ $defaultShift->name }}
                    <span class="ml-2 text-sm font-medium">· {{ substr($defaultShift->start_time, 0, 5) }} – {{ substr($defaultShift->end_time, 0, 5) }}</span>
                    <span class="ml-2 text-sm font-medium">· {{ implode(', ', $defaultShift->working_days ?? []) }}</span>
                </h3>
                <p class="text-amber-700 text-sm mt-1">All new employees are automatically assigned this shift.</p>
            </div>
        </div>
    @else
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-8 flex items-start">
            <div class="bg-yellow-100 p-2 rounded-lg mr-4">
                <i data-lucide="alert-triangle" class="w-6 h-6 text-yellow-600"></i>
            </div>
            <div>
                <h3 class="text-yellow-800 font-bold text-lg">No default shift set</h3>
                <p class="text-yellow-700 text-sm mt-1">New employees will have no shift until manually assigned.</p>
            </div>
        </div>
    @endif

    <!-- Shifts Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
        @foreach($shifts as $shift)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col relative">
                <!-- Color left border accent -->
                <div class="absolute left-0 top-0 bottom-0 w-2" style="background-color: {{ $shift->color }}"></div>
                
                <div class="p-6 pl-8 flex-grow">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-xl font-bold text-slate-800">{{ $shift->name }}</h3>
                        <div class="flex space-x-1">
                            @if($shift->is_default)
                                <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2 py-1 rounded-md flex items-center" title="Default Shift">
                                    <i data-lucide="star" class="w-3 h-3 mr-1"></i> Default
                                </span>
                            @endif
                            @if($shift->auto_assign_to_new_employees)
                                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded-md flex items-center" title="Auto Assign">
                                    <i data-lucide="user-plus" class="w-3 h-3 mr-1"></i> Auto
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="text-2xl font-light text-slate-700 mb-2">
                        {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}
                        @if($shift->crosses_midnight)
                            <span class="text-sm text-slate-400 font-medium align-top">⁺¹</span>
                        @endif
                    </div>

                    <div class="text-sm text-slate-500 mb-4 space-y-1">
                        @php
                            $start = \Carbon\Carbon::parse($shift->start_time);
                            $end = \Carbon\Carbon::parse($shift->end_time);
                            if ($shift->crosses_midnight) $end->addDay();
                            $durationHours = $start->diffInMinutes($end) / 60;
                        @endphp
                        <p class="flex items-center">
                            <i data-lucide="clock" class="w-4 h-4 mr-2 text-slate-400"></i>
                            {{ rtrim(rtrim(number_format($durationHours, 1), '0'), '.') }} hours 
                            (includes {{ $shift->break_minutes }} min break)
                        </p>
                        <p class="flex items-center">
                            <i data-lucide="users" class="w-4 h-4 mr-2 text-slate-400"></i>
                            {{ $shift->assignments_count }} employees on this shift
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-1 mt-auto">
                        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                            @if(in_array($day, $shift->working_days ?? []))
                                <span class="bg-slate-100 text-slate-600 text-xs font-medium px-2 py-1 rounded">{{ $day }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="bg-slate-50 border-t border-slate-200 p-3 pl-8 flex justify-between items-center">
                    <div class="flex space-x-2">
                        <button @click="openEditForm({{ $shift->toJson() }})" class="text-slate-600 hover:text-brand-600 px-2 py-1 text-sm font-medium">Edit</button>
                        
                        <form action="{{ route('shifts.destroy', $shift) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="{{ ($shift->is_default || $shift->assignments_count > 0) ? 'text-slate-400 cursor-not-allowed' : 'text-red-600 hover:text-red-800' }} px-2 py-1 text-sm font-medium" {{ ($shift->is_default || $shift->assignments_count > 0) ? 'disabled' : '' }}>Delete</button>
                        </form>
                    </div>

                    <div class="flex space-x-2">
                        @if(!$shift->is_default)
                            <form action="{{ route('shifts.set-default', $shift) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-slate-600 hover:text-amber-600 px-2 py-1 text-sm font-medium border border-slate-300 hover:border-amber-400 rounded bg-white">Set Default</button>
                            </form>
                        @endif

                        <button type="button" @click="openAssign({{ $shift->id }}, {{ Illuminate\Support\Js::from($shift->name) }})"
                                class="text-slate-700 hover:text-brand-600 border border-slate-300 hover:border-brand-400 bg-white px-2 py-1 text-sm font-medium rounded">Assign to some</button>

                        <form action="{{ route('shifts.assign-all', $shift) }}" method="POST" onsubmit="return confirm('This will assign this shift to all employees without a current shift. Continue?');">
                            @csrf
                            <button type="submit" class="text-white bg-slate-800 hover:bg-slate-900 px-2 py-1 text-sm font-medium rounded shadow-sm">Assign to all</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Create / Edit Form Area -->
    <div x-show="showForm" x-transition class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden" id="shift-form-section">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h2 class="text-xl font-bold text-slate-800" x-text="isEditing ? 'Edit Shift' : 'Create New Shift'"></h2>
            <button @click="closeForm()" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-6 grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <form :action="formAction" method="POST">
                    @csrf
                    <input type="hidden" name="_method" :value="isEditing ? 'PUT' : 'POST'">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Shift Name *</label>
                            <input type="text" name="name" x-model="form.name" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500" placeholder="e.g. Morning Shift">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Start Time *</label>
                            <input type="time" name="start_time" x-model="form.start_time" @change="calculateMidnight()" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">End Time *</label>
                            <input type="time" name="end_time" x-model="form.end_time" @change="calculateMidnight()" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Break Duration (minutes)</label>
                            <input type="number" name="break_minutes" x-model="form.break_minutes" required min="0" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        <div class="flex items-center mt-6">
                            <label class="flex items-center cursor-pointer">
                                <div class="relative">
                                    <input type="checkbox" name="crosses_midnight" x-model="form.crosses_midnight" class="sr-only">
                                    <div class="block bg-slate-200 w-10 h-6 rounded-full" :class="{'bg-brand-500': form.crosses_midnight}"></div>
                                    <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition" :class="{'transform translate-x-4': form.crosses_midnight}"></div>
                                </div>
                                <div class="ml-3 text-sm font-medium text-slate-700">Crosses midnight</div>
                            </label>
                        </div>
                        <div class="md:col-span-2 text-xs text-slate-500 -mt-2" x-show="form.crosses_midnight">This shift ends the next calendar day.</div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Working Days</label>
                            <div class="flex flex-wrap gap-3">
                                <template x-for="day in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="working_days[]" :value="day" x-model="form.working_days" class="rounded border-slate-300 text-brand-600 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200 focus:ring-opacity-50">
                                        <span class="ml-2 text-sm text-slate-700" x-text="day"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Shift Color</label>
                            <div class="flex gap-2">
                                <template x-for="color in ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444', '#EC4899', '#14B8A6', '#64748B']">
                                    <button type="button" @click="form.color = color" 
                                            class="w-8 h-8 rounded-full border-2 focus:outline-none"
                                            :class="form.color === color ? 'border-slate-800 ring-2 ring-offset-2 ring-slate-400' : 'border-transparent'"
                                            :style="`background-color: ${color}`"></button>
                                </template>
                            </div>
                            <input type="hidden" name="color" x-model="form.color">
                        </div>

                        <div class="md:col-span-2 border-t border-slate-200 pt-4 mt-2 space-y-4">
                            <label class="flex items-start">
                                <input type="checkbox" name="is_default" x-model="form.is_default" class="mt-1 rounded border-slate-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-slate-800">Set as default shift</span>
                                    <p class="text-xs text-slate-500">New employees will automatically be assigned this shift.</p>
                                </div>
                            </label>

                            <label class="flex items-start" x-show="form.is_default">
                                <input type="checkbox" name="auto_assign_to_new_employees" x-model="form.auto_assign_to_new_employees" class="mt-1 rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <div class="ml-3">
                                    <span class="text-sm font-medium text-slate-800">Auto-assign to new employees</span>
                                    <p class="text-xs text-slate-500">If checked, the system will actively bind this to accounts upon creation.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm transition">
                            <span x-text="isEditing ? 'Update Shift' : 'Create Shift'"></span>
                        </button>
                        <button type="button" @click="closeForm()" class="ml-3 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium py-2 px-4 rounded-lg transition">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Live Preview -->
            <div class="bg-slate-50 p-6 rounded-xl border border-slate-200 h-fit">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Live Preview</h4>
                
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col relative min-h-[160px]">
                    <div class="absolute left-0 top-0 bottom-0 w-2" :style="`background-color: ${form.color}`"></div>
                    
                    <div class="p-6 pl-8">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-xl font-bold text-slate-800" x-text="form.name || 'Shift Name'"></h3>
                        </div>

                        <div class="text-2xl font-light text-slate-700 mb-2">
                            <span x-text="form.start_time || '--:--'"></span> – <span x-text="form.end_time || '--:--'"></span>
                            <span x-show="form.crosses_midnight" class="text-sm text-slate-400 font-medium align-top">⁺¹</span>
                        </div>

                        <div class="flex flex-wrap gap-1 mt-4">
                            <template x-for="day in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']">
                                <span x-show="form.working_days.includes(day)" class="bg-slate-100 text-slate-600 text-xs font-medium px-2 py-1 rounded" x-text="day"></span>
                            </template>
                            <span x-show="form.working_days.length === 0" class="text-xs text-slate-400 italic">No days selected</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign to specific employees modal -->
    <div x-show="assignOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="assignOpen = false"></div>
        <div class="relative flex w-full max-w-lg max-h-[85vh] flex-col rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-700/60">
                <h2 class="text-base font-extrabold text-slate-900 dark:text-white">Assign “<span x-text="assignShiftName"></span>” to employees</h2>
                <button type="button" @click="assignOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <form :action="`/shifts/${assignShiftId}/assign-selected`" method="POST" class="flex min-h-0 flex-1 flex-col">
                @csrf
                <template x-for="uid in selectedUsers" :key="uid"><input type="hidden" name="user_ids[]" :value="uid"></template>

                <div class="border-b border-slate-100 p-4 dark:border-slate-700/60">
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" x-model="assignSearch" placeholder="Search by name or email…"
                               class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-brand-500 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-500 dark:text-slate-400"><span x-text="selectedUsers.length"></span> selected</span>
                        <div class="flex gap-3">
                            <button type="button" @click="selectAllVisible()" class="font-bold text-brand-600 hover:text-brand-700">Select all shown</button>
                            <button type="button" @click="selectedUsers = []" class="font-bold text-slate-500 hover:text-slate-700">Clear</button>
                        </div>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-2">
                    <template x-for="emp in filteredEmployees()" :key="emp.id">
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <input type="checkbox" :value="emp.id" x-model="selectedUsers" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800 dark:text-white" x-text="emp.name"></div>
                                <div class="truncate text-xs text-slate-400" x-text="emp.email"></div>
                            </div>
                        </label>
                    </template>
                    <div x-show="filteredEmployees().length === 0" class="py-8 text-center text-sm text-slate-400">No employees found.</div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 p-4 dark:border-slate-700/60">
                    <button type="button" @click="assignOpen = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                    <button type="submit" :disabled="selectedUsers.length === 0" :class="selectedUsers.length === 0 && 'opacity-50 cursor-not-allowed'"
                            class="rounded-xl bg-brand-600 px-5 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700">Assign to <span x-text="selectedUsers.length"></span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function shiftManager() {
    return {
        showForm: false,
        isEditing: false,
        formAction: '{{ route("shifts.store") }}',

        // --- Assign to specific employees ---
        employees: @js($employees ?? []),
        assignOpen: false,
        assignShiftId: null,
        assignShiftName: '',
        assignSearch: '',
        selectedUsers: [],
        openAssign(id, name) {
            this.assignShiftId = id;
            this.assignShiftName = name;
            this.selectedUsers = [];
            this.assignSearch = '';
            this.assignOpen = true;
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        filteredEmployees() {
            const q = this.assignSearch.trim().toLowerCase();
            if (!q) return this.employees;
            return this.employees.filter(e => e.name.toLowerCase().includes(q) || (e.email || '').toLowerCase().includes(q));
        },
        selectAllVisible() {
            this.filteredEmployees().forEach(e => { if (!this.selectedUsers.includes(e.id)) this.selectedUsers.push(e.id); });
        },

        form: {
            name: '',
            start_time: '09:00',
            end_time: '17:00',
            break_minutes: 30,
            crosses_midnight: false,
            working_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            color: '#3B82F6',
            is_default: false,
            auto_assign_to_new_employees: false
        },
        openCreateForm() {
            this.isEditing = false;
            this.formAction = '{{ route("shifts.store") }}';
            this.resetForm();
            this.showForm = true;
            setTimeout(() => {
                document.getElementById('shift-form-section').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        },
        openEditForm(shift) {
            this.isEditing = true;
            this.formAction = `/shifts/${shift.id}`;
            this.form = {
                name: shift.name,
                start_time: shift.start_time.substring(0, 5),
                end_time: shift.end_time.substring(0, 5),
                break_minutes: shift.break_minutes,
                crosses_midnight: shift.crosses_midnight,
                working_days: shift.working_days || [],
                color: shift.color,
                is_default: shift.is_default,
                auto_assign_to_new_employees: shift.auto_assign_to_new_employees
            };
            this.showForm = true;
            setTimeout(() => {
                document.getElementById('shift-form-section').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        },
        closeForm() {
            this.showForm = false;
        },
        resetForm() {
            this.form = {
                name: '',
                start_time: '09:00',
                end_time: '17:00',
                break_minutes: 30,
                crosses_midnight: false,
                working_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                color: '#3B82F6',
                is_default: false,
                auto_assign_to_new_employees: false
            };
        },
        calculateMidnight() {
            if (this.form.start_time && this.form.end_time) {
                if (this.form.end_time < this.form.start_time) {
                    this.form.crosses_midnight = true;
                } else {
                    this.form.crosses_midnight = false;
                }
            }
        }
    }
}
</script>
@endsection
