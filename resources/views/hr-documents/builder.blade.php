@extends('layouts.hr-app')

@section('title', $template->exists ? 'Edit template' : 'New template')
@section('breadcrumb', 'Documents')

@php
    $isEdit = $template->exists;
    $cfg = [
        'meta' => [
            'name'        => $template->name ?? '',
            'subtitle'    => $template->subtitle ?? '',
            'description' => $template->description ?? '',
            'icon'        => $template->icon ?? 'file-text',
            'prefill'     => $template->prefill ?? '',
            'is_active'   => (bool) ($template->is_active ?? true),
            'auto_send'   => (bool) ($template->auto_send ?? false),
            'auto_send_manager_mode' => $template->auto_send_manager_mode ?? 'none',
            'auto_send_signature_id' => (string) ($template->auto_send_signature_id ?? ''),
        ],
        'sections' => collect($template->schema ?: [[ 'title' => 'Section 1 — Details', 'fields' => [] ]])->map(fn ($s) => [
            'title'  => $s['title'] ?? 'Section',
            'fields' => collect($s['fields'] ?? [])->map(fn ($f) => [
                'id'          => $f['id'] ?? '',
                'label'       => $f['label'] ?? '',
                'type'        => $f['type'] ?? 'text',
                'width'       => $f['width'] ?? 'full',
                'optionsText' => implode("\n", $f['options'] ?? []),
                'columnsText' => implode(', ', $f['columns'] ?? []),
                'text'        => $f['text'] ?? '',
                'prefill'     => $f['prefill'] ?? '',
                'placeholder' => $f['placeholder'] ?? '',
                // Falls back to the rule that used to decide this, so a template
                // saved before the setting existed opens showing what it does today.
                'signer_role' => $f['signer_role']
                    ?? (\Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($f['label'] ?? ''), 'manager') ? 'manager' : 'employee'),
                'sign_order'  => (int) ($f['sign_order'] ?? 0),
            ])->all(),
        ])->all(),
    ];
    $inp = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 dark:bg-slate-900 dark:border-slate-600 dark:text-slate-200 focus:border-brand-500 focus:ring-1 focus:ring-brand-500';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="hrDocBuilder(@js($cfg))">

    <div class="flex items-center gap-3">
        <a href="{{ route('hr-documents.index') }}" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $isEdit ? 'Edit template' : 'New template' }}</h1>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('hr-documents.templates.update', $template) : route('hr-documents.templates.store') }}" @submit="prepare">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="schema" x-ref="schema">
        <input type="hidden" name="name" :value="meta.name">
        <input type="hidden" name="subtitle" :value="meta.subtitle">
        <input type="hidden" name="description" :value="meta.description">
        <input type="hidden" name="icon" :value="meta.icon">
        <input type="hidden" name="prefill" :value="meta.prefill">
        <input type="hidden" name="is_active" :value="meta.is_active ? 1 : 0">
        <input type="hidden" name="auto_send" :value="meta.auto_send ? 1 : 0">
        <input type="hidden" name="auto_send_manager_mode" :value="meta.auto_send_manager_mode">
        {{-- Only submitted when a signature is actually being stamped: x-show
             merely hides, and a hidden input still posts its value. --}}
        <input type="hidden" name="auto_send_signature_id"
               :value="meta.auto_send_signature_id"
               :disabled="!(meta.auto_send && meta.auto_send_manager_mode === 'self')">

        {{-- Template meta --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-4 dark:bg-slate-800 dark:border-slate-700">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Template name</label>
                    <input type="text" x-model="meta.name" placeholder="e.g. Disciplinary Note" class="{{ $inp }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Subtitle (optional)</label>
                    <input type="text" x-model="meta.subtitle" class="{{ $inp }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Description</label>
                    <input type="text" x-model="meta.description" placeholder="Shown on the template card" class="{{ $inp }}">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Auto-drafts for</label>
                    <select x-model="meta.prefill" class="{{ $inp }}">
                        <option value="">Nothing — create manually</option>
                        @foreach(\App\Services\HrDocumentAutoGenerator::TRIGGERS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400">Which attendance event auto-drafts this document.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Icon (lucide name)</label>
                    <input type="text" x-model="meta.icon" placeholder="file-text" class="{{ $inp }}">
                </div>
            </div>
        </div>

        {{-- What happens once this document has drafted itself. Only worth
             asking about for a template something actually triggers. --}}
        <template x-if="meta.prefill">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 mt-5 dark:bg-slate-800 dark:border-slate-700">
                <h3 class="text-sm font-extrabold text-slate-800 dark:text-white">When this drafts itself</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    By default it waits as a draft for somebody to read and send. You can let it go out on its own instead.
                </p>

                <div class="mt-4 space-y-2">
                    <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer"
                           :class="!meta.auto_send ? 'border-brand-400 bg-brand-50/60 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" :checked="!meta.auto_send" @change="meta.auto_send = false" class="mt-0.5 text-brand-600">
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Wait for me</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">It appears as a draft. Nothing reaches the employee until somebody opens it and sends it.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer"
                           :class="meta.auto_send ? 'border-amber-400 bg-amber-50/60 dark:bg-amber-500/10' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" :checked="meta.auto_send" @change="meta.auto_send = true" class="mt-0.5 text-amber-600">
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Send it on its own</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">It goes straight to the employee to sign, with nobody reading it first.</span>
                        </span>
                    </label>
                </div>

                <template x-if="meta.auto_send">
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <p class="text-xs text-amber-900 dark:text-amber-200">
                            <strong>Think about what this document says.</strong> A return-to-work form sending itself is
                            convenient. A lateness warning or a probation outcome sending itself, with nobody having read it,
                            is how a letter reaches the wrong person.
                        </p>

                        <div class="mt-4">
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">The manager's signature box</label>
                            <select x-model="meta.auto_send_manager_mode" class="{{ $inp }}">
                                <option value="none">Leave it blank</option>
                                <option value="manager">Ask the employee's line manager to sign</option>
                                <option value="self">Stamp a saved signature on it</option>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                Nobody is at a screen to answer this when the document is built, so it is answered here instead.
                            </p>
                        </div>

                        <template x-if="meta.auto_send_manager_mode === 'self'">
                            <div class="mt-3">
                                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Which saved signature</label>
                                <select x-model="meta.auto_send_signature_id" class="{{ $inp }}">
                                    <option value="">— pick one —</option>
                                    @foreach(($signatures ?? []) as $sig)
                                        <option value="{{ $sig->id }}">{{ $sig->name }}</option>
                                    @endforeach
                                </select>
                                @if(($signatures ?? collect())->isEmpty())
                                    <p class="mt-1 text-[11px] text-rose-600 dark:text-rose-400">
                                        You have no saved signatures yet. Add one under Documents → Signature blocks,
                                        or the manager's box will be left blank.
                                    </p>
                                @else
                                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        Stamped before the document goes out, so the employee receives it already countersigned.
                                    </p>
                                @endif
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- Sections --}}
        <div class="space-y-5 mt-5">
            <template x-for="(section, si) in sections" :key="si">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
                    <div class="flex items-center gap-3 bg-slate-900 px-4 py-2.5">
                        <i data-lucide="layers" class="h-4 w-4 text-slate-400"></i>
                        <input type="text" x-model="section.title" class="flex-1 bg-transparent text-white text-sm font-bold border-0 focus:ring-0 p-0" placeholder="Section title">
                        <button type="button" @click="removeSection(si)" class="text-slate-500 hover:text-rose-400"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </div>
                    <div class="p-4 space-y-3">
                        <template x-for="(field, fi) in section.fields" :key="fi">
                            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                <div class="grid gap-3 sm:grid-cols-12 items-start">
                                    <div class="sm:col-span-5">
                                        <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Label</label>
                                        <input type="text" x-model="field.label" class="{{ $inp }}">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Type</label>
                                        <select x-model="field.type" class="{{ $inp }}">
                                            @foreach($types as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Width</label>
                                        <select x-model="field.width" class="{{ $inp }}">
                                            <option value="full">Full</option>
                                            <option value="half">Half</option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-2 flex items-end justify-end h-full">
                                        <button type="button" @click="removeField(si, fi)" class="p-2 text-slate-300 hover:text-rose-600"><i data-lucide="x" class="h-4 w-4"></i></button>
                                    </div>

                                    <template x-if="['checkbox','radio','select'].includes(field.type)">
                                        <div class="sm:col-span-12">
                                            <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Options (one per line)</label>
                                            <textarea rows="2" x-model="field.optionsText" class="{{ $inp }}"></textarea>
                                        </div>
                                    </template>
                                    <template x-if="field.type === 'table'">
                                        <div class="sm:col-span-12">
                                            <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Columns (comma-separated)</label>
                                            <input type="text" x-model="field.columnsText" placeholder="Date, Reason" class="{{ $inp }}">
                                        </div>
                                    </template>
                                    {{-- Who signs here. Until this existed the answer was
                                         guessed from the label, so a slot called "Line
                                         Supervisor" was treated as the employee's. --}}
                                    <template x-if="field.type === 'signature'">
                                        <div class="sm:col-span-12">
                                            <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Who signs here</label>
                                            <select x-model="field.signer_role" class="{{ $inp }}">
                                                <option value="employee">The employee this document is about</option>
                                                <option value="manager">The company — line manager, or whoever sends it</option>
                                            </select>
                                            <p class="mt-1 text-[11px] text-slate-400">
                                                Call the field whatever your company calls it. This decides who is actually asked to sign.
                                            </p>
                                            <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mt-3 mb-1">Signing order</label>
                                            <select x-model.number="field.sign_order" class="{{ $inp }}">
                                                <option value="0">Any time — no order</option>
                                                <option value="1">1st to sign</option>
                                                <option value="2">2nd</option>
                                                <option value="3">3rd</option>
                                                <option value="4">4th</option>
                                            </select>
                                            <p class="mt-1 text-[11px] text-slate-400">
                                                Leave every slot on "any time" and signing works as it always has. Give them positions and each
                                                person is only asked once the one before has signed.
                                            </p>
                                        </div>
                                    </template>
                                    <template x-if="field.type === 'note'">
                                        <div class="sm:col-span-12">
                                            <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Note text</label>
                                            <textarea rows="2" x-model="field.text" class="{{ $inp }}"></textarea>
                                        </div>
                                    </template>
                                    {{-- Anything that holds a value can be auto-filled. Only a
                                         signature and a fixed note cannot, so exclude just those
                                         rather than listing types and forgetting one. --}}
                                    <template x-if="!['signature','note'].includes(field.type)">
                                        <div class="sm:col-span-12">
                                            <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-400 mb-1">Auto-fill from attendance (optional)</label>
                                            <select x-model="field.prefill" class="{{ $inp }}">
                                                <option value="">None</option>
                                                <option value="employee_name">Employee name</option>
                                                <option value="job_title">Job title</option>
                                                <option value="department">Department</option>
                                                <option value="line_manager">Line manager</option>
                                                <option value="late_count">Late days count</option>
                                                <option value="lateness_dates">Lateness dates (list)</option>
                                                <option value="lateness_summary">Lateness dates + reasons (text)</option>
                                                <option value="late_minutes_total">Total time late</option>
                                                <option value="absent_count">Absent days count</option>
                                                <option value="lateness_table">Lateness table (dates)</option>
                                                <option value="absence_table">Absence table (dates)</option>
                                                <option value="leave_table">Leave table (dates, days, reasons)</option>
                                                <option value="leave_return_table">Leave table + return notes</option>
                                                <option value="leave_return_summary">Return notes (text)</option>
                                                <option value="leave_start_date">This leave — first day</option>
                                                <option value="leave_return_date">This leave — day back at work</option>
                                                <option value="leave_reason">This leave — reason given on applying</option>
                                                <option value="leave_return_note">This leave — note given on returning</option>
                                                <optgroup label="Pay &amp; probation">
                                                    <option value="previous_salary">Pay — previous salary</option>
                                                    <option value="new_salary">Pay — new salary</option>
                                                    <option value="salary_increase">Pay — increase amount</option>
                                                    <option value="salary_increase_percent">Pay — increase %</option>
                                                    <option value="pay_change_date">Pay — effective date</option>
                                                    <option value="pay_change_reason">Pay — reason given</option>
                                                    <option value="previous_job_title">Pay — job title before the change</option>
                                                    <option value="new_job_title">Pay — job title after the change</option>
                                                    <option value="probation_start">Probation — start date</option>
                                                    <option value="probation_end">Probation — end date</option>
                                                </optgroup>
                                                {{-- Everything this workspace tracks on an employee profile.
                                                     Add a field to your profile template and it appears here. --}}
                                                @foreach(($profileTokens ?? []) as $group => $tokens)
                                                    <optgroup label="{{ $group }}">
                                                        @foreach($tokens as $token => $label)
                                                            <option value="{{ $token }}">{{ $label }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                                <option value="leave_count">Leave count</option>
                                                <option value="leave_days">Leave days total</option>
                                            </select>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <button type="button" @click="addField(si)" class="w-full rounded-xl border border-dashed border-slate-300 py-2.5 text-xs font-semibold text-slate-500 hover:border-brand-400 hover:text-brand-600 transition dark:border-slate-600">
                            <i data-lucide="plus" class="inline h-3.5 w-3.5"></i> Add field
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <button type="button" @click="addSection()" class="mt-4 w-full rounded-xl border border-dashed border-slate-300 py-3 text-sm font-semibold text-slate-500 hover:border-brand-400 hover:text-brand-600 transition dark:border-slate-600">
            <i data-lucide="plus" class="inline h-4 w-4"></i> Add section
        </button>

        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('hr-documents.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Cancel</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition">
                <i data-lucide="save" class="h-4 w-4"></i> {{ $isEdit ? 'Save changes' : 'Create template' }}
            </button>
        </div>
    </form>
</div>

<script>
    function hrDocBuilder(cfg) {
        return {
            meta: cfg.meta,
            sections: cfg.sections,

            addSection() { this.sections.push({ title: 'New section', fields: [] }); },
            removeSection(i) { this.sections.splice(i, 1); },
            addField(si) { this.sections[si].fields.push({ id: '', label: '', type: 'text', width: 'full', optionsText: '', columnsText: '', text: '', prefill: '', placeholder: '', signer_role: 'employee', sign_order: 0 }); },
            removeField(si, fi) { this.sections[si].fields.splice(fi, 1); },

            buildSchema() {
                return this.sections.map(s => ({
                    title: s.title,
                    fields: (s.fields || []).map(f => {
                        const o = { id: f.id || '', label: f.label || '', type: f.type, width: f.width || 'full' };
                        if (['checkbox', 'radio', 'select'].includes(f.type)) o.options = (f.optionsText || '').split('\n').map(x => x.trim()).filter(Boolean);
                        if (f.type === 'table') o.columns = (f.columnsText || '').split(',').map(x => x.trim()).filter(Boolean);
                        if (f.type === 'note') o.text = f.text || '';
                        if (f.type === 'signature') { o.signer_role = f.signer_role || 'employee'; o.sign_order = Number(f.sign_order) || 0; }
                        if (f.prefill) o.prefill = f.prefill;
                        if (f.placeholder) o.placeholder = f.placeholder;
                        return o;
                    }),
                }));
            },
            prepare() { this.$refs.schema.value = JSON.stringify(this.buildSchema()); return true; },
        };
    }
</script>
@endsection
