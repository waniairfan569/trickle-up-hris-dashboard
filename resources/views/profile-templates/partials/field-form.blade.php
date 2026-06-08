@php
    $uniqueId = 'field_form_' . ($section->id) . '_' . ($field?->id ?? 'new');
@endphp

<div id="{{ $uniqueId }}" class="bg-slate-50 border border-slate-200/60 rounded-xl p-5 dark:bg-slate-850 dark:border-slate-750">
    @if ($field)
        <form action="{{ route('profile-fields.update', $field->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
    @else
        <form action="{{ route('profile-sections.fields.store', $section->id) }}" method="POST" class="space-y-6">
            @csrf
    @endif

        <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-700/60">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ $field ? 'Edit Field: ' . $field->name : 'Add New Field to ' . $section->name }}
            </h4>
            @if ($field?->is_system)
                <span class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-650 dark:bg-slate-800 dark:text-slate-400">
                    <i data-lucide="shield" class="h-3 w-3"></i> System Locked
                </span>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <!-- Field Name -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Field Label <span class="text-rose-550">*</span></label>
                <input type="text" name="name" value="{{ old('name', $field?->name) }}" required placeholder="e.g., Office Location, Github Handle"
                    class="field-name-input block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 placeholder-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
            </div>

            <!-- Field Key -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Field Key <span class="text-rose-550">*</span></label>
                <input type="text" name="key" value="{{ old('key', $field?->key) }}" required placeholder="e.g., office_location, github_handle"
                    {{ $field ? 'readonly disabled' : '' }}
                    class="field-key-input block w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-2.5 text-sm text-slate-650 placeholder-slate-450 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
                @if(!$field)
                    <p class="text-[10px] text-slate-400 leading-tight">Unique identifier generated automatically from label. Alphanumeric and underscores only.</p>
                @endif
            </div>

            <!-- Field Type -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Field Type <span class="text-rose-550">*</span></label>
                @if($field)
                    <input type="hidden" name="type" value="{{ $field->type }}">
                @endif
                <select name="type" {{ $field ? 'disabled' : 'required' }}
                    class="field-type-select block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
                    <option value="text" {{ (old('type', $field?->type) == 'text') ? 'selected' : '' }}>Single-line Text</option>
                    <option value="textarea" {{ (old('type', $field?->type) == 'textarea') ? 'selected' : '' }}>Multi-line Text (Textarea)</option>
                    <option value="number" {{ (old('type', $field?->type) == 'number') ? 'selected' : '' }}>Number</option>
                    <option value="date" {{ (old('type', $field?->type) == 'date') ? 'selected' : '' }}>Date</option>
                    <option value="date_range" {{ (old('type', $field?->type) == 'date_range') ? 'selected' : '' }}>Date Range</option>
                    <option value="dropdown" {{ (old('type', $field?->type) == 'dropdown') ? 'selected' : '' }}>Dropdown Selection</option>
                    <option value="multi_select" {{ (old('type', $field?->type) == 'multi_select') ? 'selected' : '' }}>Multi-select Dropdown</option>
                    <option value="checkbox" {{ (old('type', $field?->type) == 'checkbox') ? 'selected' : '' }}>Checkbox Toggle</option>
                    <option value="phone" {{ (old('type', $field?->type) == 'phone') ? 'selected' : '' }}>Phone Number</option>
                    <option value="email" {{ (old('type', $field?->type) == 'email') ? 'selected' : '' }}>Email Address</option>
                    <option value="url" {{ (old('type', $field?->type) == 'url') ? 'selected' : '' }}>Website URL</option>
                    <option value="file" {{ (old('type', $field?->type) == 'file') ? 'selected' : '' }}>File Upload</option>
                    <option value="currency" {{ (old('type', $field?->type) == 'currency') ? 'selected' : '' }}>Currency Value</option>
                    <option value="employee_lookup" {{ (old('type', $field?->type) == 'employee_lookup') ? 'selected' : '' }}>Employee Lookup</option>
                    <option value="department_lookup" {{ (old('type', $field?->type) == 'department_lookup') ? 'selected' : '' }}>Department Lookup</option>
                </select>
            </div>

            <!-- Placeholder -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Placeholder / Guidelines</label>
                <input type="text" name="placeholder" value="{{ old('placeholder', $field?->placeholder) }}" placeholder="e.g., Select from list, or Enter URL..."
                    class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 placeholder-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
            </div>
        </div>

        <!-- Dynamic Options Container for select elements -->
        <div class="options-container-wrapper hidden space-y-3 border-t border-slate-100 pt-4 dark:border-slate-750">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Dropdown Selection Options <span class="text-rose-550">*</span></label>
            <div class="flex items-center gap-x-2">
                <input type="text" placeholder="Type option value and press Enter or click Add"
                    class="option-tag-input block w-full rounded-xl border border-slate-200 px-4 py-2 text-sm text-slate-950 placeholder-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 transition duration-150">
                <button type="button" class="add-option-btn inline-flex items-center justify-center rounded-xl bg-indigo-50 border border-indigo-200 px-4 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition dark:bg-indigo-950/20 dark:border-indigo-900/30 dark:text-indigo-400">
                    Add
                </button>
            </div>
            
            <!-- List of Option Pills -->
            <div class="option-pills-wrapper flex flex-wrap gap-2 pt-2">
                <!-- Javascript will render pills here -->
            </div>

            <!-- Container for actual hidden inputs -->
            <div class="hidden-options-inputs">
                <!-- Javascript will put <input type="hidden" name="options[]"> here -->
            </div>
            <p class="text-[10px] text-slate-400 leading-tight">Must add at least one option for user selection.</p>
        </div>

        <!-- Settings Checkboxes -->
        <div class="grid grid-cols-1 gap-5 border-t border-slate-100 pt-4 dark:border-slate-750 sm:grid-cols-2">
            <div class="flex items-start gap-x-3">
                <div class="flex h-5 items-center">
                    <input type="checkbox" name="is_required" id="is_required_{{ $uniqueId }}" value="1" {{ old('is_required', $field?->is_required) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850">
                </div>
                <div class="text-xs">
                    <label for="is_required_{{ $uniqueId }}" class="font-bold text-slate-700 dark:text-slate-350">Mandatory / Required</label>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500">Employee/HR cannot complete profile without filling this.</p>
                </div>
            </div>

            <div class="flex items-start gap-x-3">
                <div class="flex h-5 items-center">
                    <input type="checkbox" name="employee_can_edit" id="employee_can_edit_{{ $uniqueId }}" value="1" {{ old('employee_can_edit', $field?->employee_can_edit ?? true) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850">
                </div>
                <div class="text-xs">
                    <label for="employee_can_edit_{{ $uniqueId }}" class="font-bold text-slate-700 dark:text-slate-350">Employee Can Edit</label>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500">Allow standard employees to edit this field from their own portal.</p>
                </div>
            </div>
        </div>

        <!-- Visibility Radio Cards -->
        <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-750">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Access & Visibility Control</label>
            
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Public -->
                <label class="relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none hover:border-slate-300 transition dark:bg-slate-800 dark:border-slate-700">
                    <input type="radio" name="visibility" value="public" class="sr-only" {{ old('visibility', $field?->visibility ?? 'public') === 'public' ? 'checked' : '' }}>
                    <div class="flex flex-col">
                        <span class="block text-xs font-bold text-slate-900 dark:text-white">Public</span>
                        <span class="mt-1 block text-[10px] text-slate-400 leading-tight">Visible to everyone in the company.</span>
                    </div>
                </label>

                <!-- Internal -->
                <label class="relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none hover:border-slate-300 transition dark:bg-slate-800 dark:border-slate-700">
                    <input type="radio" name="visibility" value="internal" class="sr-only" {{ old('visibility', $field?->visibility) === 'internal' ? 'checked' : '' }}>
                    <div class="flex flex-col">
                        <span class="block text-xs font-bold text-slate-900 dark:text-white">Internal</span>
                        <span class="mt-1 block text-[10px] text-slate-400 leading-tight">Employee, managers, and HR only (peers hidden).</span>
                    </div>
                </label>

                <!-- Private -->
                <label class="relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none hover:border-slate-300 transition dark:bg-slate-800 dark:border-slate-700">
                    <input type="radio" name="visibility" value="private" class="sr-only" {{ old('visibility', $field?->visibility) === 'private' ? 'checked' : '' }}>
                    <div class="flex flex-col">
                        <span class="block text-xs font-bold text-slate-900 dark:text-white">Private</span>
                        <span class="mt-1 block text-[10px] text-slate-400 leading-tight">Only the employee and HR admins can see.</span>
                    </div>
                </label>

                <!-- Manager Only -->
                <label class="relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none hover:border-slate-300 transition dark:bg-slate-800 dark:border-slate-700">
                    <input type="radio" name="visibility" value="manager" class="sr-only" {{ old('visibility', $field?->visibility) === 'manager' ? 'checked' : '' }}>
                    <div class="flex flex-col">
                        <span class="block text-xs font-bold text-slate-900 dark:text-white">Manager Only</span>
                        <span class="mt-1 block text-[10px] text-slate-400 leading-tight">Managers and HR only (employee hidden).</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Encryption Options Wrapper -->
        <div class="encryption-container-wrapper hidden space-y-3 border-t border-slate-100 pt-4 dark:border-slate-750">
            <div class="flex items-start gap-x-3 rounded-xl border border-amber-250 bg-amber-50/50 p-4 dark:border-amber-900/30 dark:bg-amber-950/10">
                <div class="flex h-5 items-center">
                    <input type="checkbox" name="is_encrypted" id="is_encrypted_{{ $uniqueId }}" value="1" 
                        {{ old('is_encrypted', $field?->is_encrypted) ? 'checked' : '' }}
                        {{ $field ? 'readonly disabled' : '' }}
                        class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-850">
                </div>
                <div class="text-xs">
                    <label for="is_encrypted_{{ $uniqueId }}" class="font-bold text-amber-850 dark:text-amber-400">Database Encryption (AES-256)</label>
                    <p class="text-[10px] text-amber-700 dark:text-amber-500 leading-normal mt-0.5">Encrypts the field content at rest in the database. Recommended for highly sensitive information (e.g. National ID, Salary specifics). Warning: Once set, database-level encryption cannot be disabled.</p>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-x-3 border-t border-slate-150 pt-4 dark:border-slate-750">
            <button type="button" class="cancel-field-form-btn inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                Cancel
            </button>
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 hover:shadow-lg transition duration-150">
                {{ $field ? 'Save Changes' : 'Add Field' }}
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const container = document.getElementById('{{ $uniqueId }}');
    if (!container) return;

    const nameInput = container.querySelector('.field-name-input');
    const keyInput = container.querySelector('.field-key-input');
    const typeSelect = container.querySelector('.field-type-select');
    const optionsContainer = container.querySelector('.options-container-wrapper');
    const encryptionContainer = container.querySelector('.encryption-container-wrapper');
    const cancelBtn = container.querySelector('.cancel-field-form-btn');
    
    // Slugification (only when adding a new field)
    @if(!$field)
    if (nameInput && keyInput) {
        nameInput.addEventListener('input', function() {
            let val = nameInput.value.toLowerCase();
            // Allow only alphanumeric, spaces, hyphens, and underscores
            val = val.replace(/[^a-z0-9\s-_]/g, '');
            // Convert multiple spaces/hyphens to single underscore
            val = val.replace(/[\s-]+/g, '_');
            // Trim underscores from ends
            val = val.replace(/^_+|_+$/g, '');
            keyInput.value = val;
        });
    }
    @endif

    // Show/hide options input and encryption checkbox based on type selection
    function toggleDynamicFields() {
        if (!typeSelect) return;
        const selectedType = typeSelect.value;
        
        // Options list toggle: dropdown or multi_select
        if (selectedType === 'dropdown' || selectedType === 'multi_select') {
            optionsContainer.classList.remove('hidden');
            const hiddenInputs = container.querySelectorAll('input[name="options[]"]');
            if(hiddenInputs.length === 0) {
                // Prepopulate a couple dummy values if empty to show styling
            }
        } else {
            optionsContainer.classList.add('hidden');
        }

        // Encryption option toggle: text, textarea, number
        if (encryptionContainer) {
            if (selectedType === 'text' || selectedType === 'textarea' || selectedType === 'number') {
                encryptionContainer.classList.remove('hidden');
            } else {
                encryptionContainer.classList.add('hidden');
            }
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleDynamicFields);
        toggleDynamicFields(); // initial run
    }

    // Toggle styling for selected Visibility radio card
    const radioInputs = container.querySelectorAll('input[type="radio"][name="visibility"]');
    function updateRadioStyles() {
        radioInputs.forEach(input => {
            const card = input.closest('label');
            if (input.checked) {
                card.className = 'relative flex cursor-pointer rounded-xl border border-brand-500 bg-brand-50/10 p-4 shadow-sm focus:outline-none transition dark:bg-brand-950/10 dark:border-brand-500';
            } else {
                card.className = 'relative flex cursor-pointer rounded-xl border border-slate-200 bg-white p-4 shadow-sm focus:outline-none hover:border-slate-300 transition dark:bg-slate-800 dark:border-slate-700';
            }
        });
    }
    
    radioInputs.forEach(input => {
        input.addEventListener('change', updateRadioStyles);
    });
    updateRadioStyles(); // initial run

    // Options tag system
    const optionInput = container.querySelector('.option-tag-input');
    const addOptionBtn = container.querySelector('.add-option-btn');
    const pillsWrapper = container.querySelector('.option-pills-wrapper');
    const hiddenInputsContainer = container.querySelector('.hidden-options-inputs');

    let currentOptions = {!! json_encode($field?->options ?? []) !!};

    function renderOptions() {
        if (!pillsWrapper || !hiddenInputsContainer) return;
        pillsWrapper.innerHTML = '';
        hiddenInputsContainer.innerHTML = '';

        if(currentOptions.length === 0) {
            pillsWrapper.innerHTML = '<span class="text-slate-400 dark:text-slate-500 text-xs italic">No selection options added yet.</span>';
        }

        currentOptions.forEach((opt, idx) => {
            // Create pill element
            const pill = document.createElement('span');
            pill.className = 'inline-flex items-center gap-x-1 rounded-lg bg-brand-50 px-2 py-1 text-xs font-bold text-brand-700 border border-brand-100 dark:bg-brand-950/20 dark:text-brand-400 dark:border-brand-900/30';
            
            const textSpan = document.createElement('span');
            textSpan.textContent = opt;
            pill.appendChild(textSpan);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-brand-400 hover:text-brand-650 font-bold ml-1 transition focus:outline-none';
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                currentOptions.splice(idx, 1);
                renderOptions();
            });
            pill.appendChild(removeBtn);
            pillsWrapper.appendChild(pill);

            // Create hidden input
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'options[]';
            hiddenInput.value = opt;
            hiddenInputsContainer.appendChild(hiddenInput);
        });
    }

    function addOption() {
        if (!optionInput) return;
        const val = optionInput.value.trim();
        if (val && !currentOptions.includes(val)) {
            currentOptions.push(val);
            optionInput.value = '';
            renderOptions();
        }
    }

    if (optionInput && addOptionBtn) {
        addOptionBtn.addEventListener('click', (e) => {
            e.preventDefault();
            addOption();
        });

        optionInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addOption();
            } else if (e.key === ',') {
                e.preventDefault();
                addOption();
            }
        });
    }

    // Initialize option pills if any
    renderOptions();

    // Cancel Button logic (collapses the form inline)
    if (cancelBtn) {
        cancelBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Find closest parent wrapper and hide it
            const inlineFormWrapper = container.closest('.inline-field-form-wrapper');
            if (inlineFormWrapper) {
                inlineFormWrapper.classList.add('hidden');
            } else {
                container.classList.add('hidden');
            }
        });
    }
})();
</script>
