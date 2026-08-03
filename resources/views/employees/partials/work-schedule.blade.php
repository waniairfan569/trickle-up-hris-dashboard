@php
    $shiftService = app(\App\Services\ShiftService::class);
    $currentShift = clone $employee; // need the shift info
    $activeAssignment = $employee->shiftAssignments()
        ->where('assignment_type', 'recurring')
        ->whereNull('recurring_end_date')
        ->first();
    
    $defaultShift = \App\Models\Shift::getDefault();
    $shifts = \App\Models\Shift::where('is_active', true)->get();
@endphp

<div class="mb-8 rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden dark:bg-slate-900 dark:border-slate-800" x-data="{ showChangeShift: false, shiftType: 'recurring' }">
    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-5 dark:border-slate-800 dark:bg-slate-850/50 flex justify-between items-center">
        <div>
            <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="clock" class="h-5 w-5 text-slate-400"></i>
                Work Schedule
            </h2>
            <p class="text-sm text-slate-500 mt-1">Current recurring shift assigned to this employee</p>
        </div>
        
        @if(auth()->user()->isAdmin() || auth()->user()->isManager())
            <button type="button" @click="showChangeShift = !showChangeShift" class="bg-white border-2 border-slate-200 hover:bg-slate-50 text-slate-700 font-bold py-1.5 px-4 rounded-lg transition text-sm">
                Change Shift
            </button>
        @endif
    </div>

    <div class="p-6">
        @if($activeAssignment)
            <div class="flex items-start">
                <div class="w-1.5 h-full rounded-full self-stretch mr-4" style="background-color: {{ $activeAssignment->shift->color }}"></div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center">
                        {{ $activeAssignment->shift->name }}
                        <span class="ml-3 text-sm font-medium text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">
                            {{ substr($activeAssignment->shift->start_time, 0, 5) }} – {{ substr($activeAssignment->shift->end_time, 0, 5) }}
                        </span>
                    </h3>
                    
                    <div class="text-sm text-slate-500 mt-2 space-y-1">
                        <p class="flex items-center">
                            <i data-lucide="calendar" class="w-4 h-4 mr-2 text-slate-400"></i>
                            {{ implode(', ', $activeAssignment->recurring_days ?? []) }}
                        </p>
                        <p class="flex items-center">
                            <i data-lucide="info" class="w-4 h-4 mr-2 text-slate-400"></i>
                            Assigned since {{ $activeAssignment->recurring_start_date->format('M d, Y') }} 
                            (Recurring — no end date)
                        </p>
                    </div>
                </div>
            </div>
        @elseif($defaultShift)
            <div class="bg-amber-50 border border-amber-200 p-4 rounded-xl">
                <div class="flex items-center text-amber-800 mb-1">
                    <i data-lucide="alert-triangle" class="w-5 h-5 mr-2 text-amber-600"></i>
                    <h4 class="font-bold">No personal shift assigned</h4>
                </div>
                <p class="text-amber-700 text-sm mb-2">This employee is falling back to the default schedule:</p>
                <div class="bg-white bg-opacity-60 p-3 rounded-lg flex items-center">
                    <div class="w-3 h-3 rounded-full mr-3" style="background-color: {{ $defaultShift->color }}"></div>
                    <span class="font-bold text-slate-800 mr-2">{{ $defaultShift->name }}</span>
                    <span class="text-slate-600 text-sm">{{ substr($defaultShift->start_time, 0, 5) }} – {{ substr($defaultShift->end_time, 0, 5) }}</span>
                </div>
            </div>
        @else
            <div class="text-slate-500 italic">No shift assigned and no default shift set.</div>
        @endif

        <!-- Inline Change Shift Form -->
        <div x-show="showChangeShift" x-transition class="mt-6 pt-6 border-t border-slate-200">
            <h4 class="font-bold text-slate-800 mb-4">Assign New Shift</h4>
            
            <!-- Type Selector -->
            <div class="flex space-x-4 mb-6">
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="assignment_type_toggle" value="recurring" x-model="shiftType" class="text-brand-600 focus:ring-brand-500">
                    <span class="ml-2 text-sm font-medium text-slate-700">Recurring Schedule</span>
                </label>
                <label class="flex items-center cursor-pointer">
                    <input type="radio" name="assignment_type_toggle" value="single" x-model="shiftType" class="text-brand-600 focus:ring-brand-500">
                    <span class="ml-2 text-sm font-medium text-slate-700">Single Day Override</span>
                </label>
            </div>

            <!-- Recurring Form (form-attr + teleported shell so it isn't nested in #profile-edit-form) -->
            <div x-show="shiftType === 'recurring'" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Select Shift *</label>
                        <select name="shift_id" form="shift-recurring-{{ $employee->id }}" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach($shifts as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ substr($s->start_time, 0, 5) }} – {{ substr($s->end_time, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Start Date *</label>
                        <input type="date" name="recurring_start_date" form="shift-recurring-{{ $employee->id }}" required value="{{ date('Y-m-d') }}" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Working Days *</label>
                        <div class="flex flex-wrap gap-3">
                            @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="recurring_days[]" form="shift-recurring-{{ $employee->id }}" value="{{ $day }}" class="rounded border-slate-300 text-brand-600 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200 focus:ring-opacity-50" checked>
                                    <span class="ml-2 text-sm text-slate-700">{{ $day }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" form="shift-recurring-{{ $employee->id }}" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm transition text-sm">Save Recurring Assignment</button>
                </div>
            </div>
            <template x-teleport="body"><form id="shift-recurring-{{ $employee->id }}" action="{{ route('employees.shifts.assign.recurring', $employee) }}" method="POST">@csrf</form></template>

            <!-- Single Day Form -->
            <div x-show="shiftType === 'single'" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Select Shift *</label>
                        <select name="shift_id" form="shift-single-{{ $employee->id }}" required class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach($shifts as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ substr($s->start_time, 0, 5) }} – {{ substr($s->end_time, 0, 5) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Specific Date *</label>
                        <input type="date" name="date" form="shift-single-{{ $employee->id }}" required value="{{ date('Y-m-d') }}" class="w-full border-slate-300 rounded-lg shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="flex justify-end pt-2">
                    <button type="submit" form="shift-single-{{ $employee->id }}" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-6 rounded-lg shadow-sm transition text-sm">Save Single Override</button>
                </div>
            </div>
            <template x-teleport="body"><form id="shift-single-{{ $employee->id }}" action="{{ route('employees.shifts.assign.single', $employee) }}" method="POST">@csrf</form></template>
        </div>
    </div>
</div>
