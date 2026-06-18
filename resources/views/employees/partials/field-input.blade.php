@php
    $inputClasses = "w-full border border-slate-200 rounded-lg px-3.5 py-2.5 text-sm focus:ring-1 focus:ring-brand-500 focus:border-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-100";
    $checkboxClasses = "rounded border-slate-350 text-brand-600 focus:ring-brand-500 h-4 w-4 dark:bg-slate-900 dark:border-slate-700";
@endphp

<div class="flex flex-col gap-1.5">
    <label class="text-xs text-slate-500 font-bold dark:text-slate-400">
        {{ $field->name }}
        @if($field->is_encrypted)
            <span class="text-[9px] text-emerald-500 ml-1 font-normal">(Encrypted)</span>
        @endif
    </label>

    @switch($field->type)
        @case('textarea')
            <textarea name="fields[{{ $field->key }}]" 
                      rows="3" 
                      class="{{ $inputClasses }}" 
                      placeholder="{{ $field->placeholder }}"
                      >{{ old('fields.'.$field->key, $value) }}</textarea>
            @break

        @case('date')
            <input type="date" 
                   name="fields[{{ $field->key }}]" 
                   value="{{ old('fields.'.$field->key, $value ? ($value instanceof \Carbon\Carbon ? $value->format('Y-m-d') : substr($value, 0, 10)) : '') }}" 
                   class="{{ $inputClasses }}">
            @break

        @case('number')
        @case('currency')
            <input type="number" 
                   name="fields[{{ $field->key }}]" 
                   value="{{ old('fields.'.$field->key, $value) }}" 
                   step="{{ $field->type === 'currency' ? '0.01' : '1' }}" 
                   class="{{ $inputClasses }}" 
                   placeholder="{{ $field->placeholder }}">
            @break

        @case('dropdown')
            <select name="fields[{{ $field->key }}]" 
                    class="{{ $inputClasses }}">
                <option value="">Select...</option>
                @if(is_array($field->options))
                    @foreach($field->options as $opt)
                        <option value="{{ $opt }}" {{ old('fields.'.$field->key, $value) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                @endif
            </select>
            @break

        @case('multi_select')
            @php
                $sel = (array)(is_string($value) ? json_decode($value, true) : $value);
            @endphp
            <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 rounded-lg dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                @if(is_array($field->options))
                    @foreach($field->options as $opt)
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-350 cursor-pointer">
                            <input type="checkbox" 
                                   name="fields[{{ $field->key }}][]" 
                                   value="{{ $opt }}" 
                                   class="{{ $checkboxClasses }}"
                                   {{ in_array($opt, $sel) ? 'checked' : '' }}>
                            <span>{{ $opt }}</span>
                        </label>
                    @endforeach
                @endif
            </div>
            @break

        @case('checkbox')
            <div class="flex items-center h-full pt-1">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-350 cursor-pointer">
                    <input type="checkbox" 
                           name="fields[{{ $field->key }}]" 
                           value="1" 
                           class="{{ $checkboxClasses }}"
                           {{ old('fields.'.$field->key, $value) ? 'checked' : '' }}>
                    <span>Yes, enable this option</span>
                </label>
            </div>
            @break

        @case('file')
            <div class="space-y-2">
                @if($value)
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs border border-slate-150 dark:bg-slate-900 dark:border-slate-850">
                        <span class="text-slate-550 truncate max-w-xs dark:text-slate-400">Current: {{ basename($value) }}</span>
                        <a href="{{ asset('storage/' . $value) }}" target="_blank" class="inline-flex items-center gap-1 font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400">
                            <i data-lucide="download" class="h-3.5 w-3.5"></i>
                            <span>View</span>
                        </a>
                    </div>
                @endif
                <input type="file" 
                       name="fields[{{ $field->key }}]" 
                       class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-900/20 dark:file:text-brand-400"
                       >
            </div>
            @break

        @case('employee_lookup')
            @php
                // Bug #4: Exclude the current employee so they cannot set themselves as manager
                $allUsersForLookup = isset($allUsers)
                    ? $allUsers
                    : \App\Models\User::orderBy('first_name')->where('id', '!=', optional($employee)->id ?? 0)->with('department')->get();
            @endphp
            <select name="fields[{{ $field->key }}]" 
                    class="{{ $inputClasses }}">
                <option value="">Select Employee...</option>
                @foreach($allUsersForLookup as $u)
                    <option value="{{ $u->id }}" {{ intval(old('fields.'.$field->key, $value)) === intval($u->id) ? 'selected' : '' }}>
                        {{ $u->full_name }}
                    </option>
                @endforeach
            </select>
            @break

        @case('department_lookup')
            @php
                $allDepartments = \App\Models\Department::orderBy('name')->get();
            @endphp
            <select name="fields[{{ $field->key }}]" 
                    class="{{ $inputClasses }}">
                <option value="">Select Department...</option>
                @foreach($allDepartments as $d)
                    <option value="{{ $d->id }}" {{ (string)old('fields.'.$field->key, $value) === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
            @break

        @default
            <input type="{{ in_array($field->type, ['email', 'phone', 'url']) ? ($field->type === 'phone' ? 'tel' : $field->type) : 'text' }}" 
                   name="fields[{{ $field->key }}]" 
                   value="{{ old('fields.'.$field->key, $value) }}" 
                   class="{{ $inputClasses }}" 
                   placeholder="{{ $field->placeholder ?? '' }}">
    @endswitch

    {{-- Bug #9: Helper text for specific fields --}}
    @if($field->key === 'end_date' || $field->key === 'employment_end_date')
        <p class="text-xs text-slate-400 mt-1">Leave blank if currently employed. Required for terminated or offboarded employees.</p>
    @endif
    @if($field->key === 'employee_id')
        <p class="text-xs text-slate-400 mt-1">e.g. EMP-001 — must be unique across the organisation.</p>
    @endif
</div>
