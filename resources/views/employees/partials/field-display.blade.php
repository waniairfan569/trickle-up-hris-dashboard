@php $isSensitive = isset($field) && in_array($field->visibility ?? 'public', ['private', 'internal', 'manager']); @endphp
<div class="flex flex-col gap-0.5">
    <span class="text-xs text-slate-400 font-medium dark:text-slate-500 flex items-center gap-1">
        <span>{{ $field->name }}</span>
        @if($field->is_encrypted)
            <i data-lucide="shield-check" class="h-3 w-3 text-emerald-500 inline-block" title="Encrypted at rest"></i>
        @endif
    </span>

    <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">
        @if($isSensitive)
            <span x-show="!showSensitive" class="text-slate-400 tracking-widest select-none">••••••••</span>
        @endif
        <span @if($isSensitive) x-show="showSensitive" x-cloak @endif>
        @if(is_null($value) || $value === '')
            <span class="text-slate-400 font-medium italic">—</span>
        @else
            @switch($field->type)
                @case('file')
                    <div class="flex items-center gap-2">
                        <i data-lucide="paperclip" class="h-4 w-4 text-slate-400"></i>
                        <a href="{{ asset('storage/' . $value) }}" target="_blank" class="text-brand-600 hover:text-brand-700 transition dark:text-brand-400 underline font-bold" download>
                            Download Document
                        </a>
                    </div>
                    @break

                @case('multi_select')
                    @php
                        $tags = is_string($value) ? json_decode($value, true) : $value;
                    @endphp
                    @if(is_array($tags) && count($tags) > 0)
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($tags as $tag)
                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-950">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-slate-400 font-medium italic">—</span>
                    @endif
                    @break

                @case('checkbox')
                    {{ $value ? 'Yes' : 'No' }}
                    @break

                @case('date')
                    @php
                        try {
                            $dateVal = \Carbon\Carbon::parse($value);
                            $formattedDate = $dateVal->format('d M Y');
                        } catch (\Exception $e) {
                            $formattedDate = $value;
                        }
                    @endphp
                    {{ $formattedDate }}
                    @break

                @case('employee_lookup')
                    @php
                        $emp = \App\Models\User::find($value);
                    @endphp
                    @if($emp)
                        <a href="{{ route('employees.profile', $emp->id) }}" class="text-brand-600 hover:text-brand-700 transition dark:text-brand-400 underline">
                            {{ $emp->full_name }}
                        </a>
                    @else
                        <span class="text-slate-400 font-medium italic">Not assigned</span>
                    @endif
                    @break

                @case('department_lookup')
                    @php
                        $dept = \App\Models\Department::find($value);
                    @endphp
                    {{ $dept ? $dept->name : 'Not assigned' }}
                    @break

                @case('currency')
                    @php
                        // Check if currency field value is specified
                        $currency = $employee->getFieldValue('currency') ?? 'GBP';
                        $symbol = match($currency) {
                            'USD' => '$',
                            'EUR' => '€',
                            'PKR' => '₨',
                            default => '£'
                        };
                        // Bug #11: Hide decimals for whole-number salaries
                        $numericVal = (float)$value;
                        $formattedSalary = fmod($numericVal, 1) == 0
                            ? number_format($numericVal, 0)
                            : number_format($numericVal, 2);
                    @endphp
                    {{ $symbol }}{{ $formattedSalary }}
                    @break

                @default
                    @php
                        // Only title-case known enum/status fields; leave everything else verbatim
                        $enumKeys = ['employee_status', 'employment_status', 'contract_type', 'employment_type', 'work_location', 'pay_frequency', 'gender'];
                        $shouldTitleCase = isset($field) && in_array($field->key, $enumKeys);
                    @endphp
                    @if($shouldTitleCase && is_string($value))
                        {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $value)) }}
                    @else
                        {{ $value }}
                    @endif
            @endswitch
        @endif
        </span>
    </span>
</div>
