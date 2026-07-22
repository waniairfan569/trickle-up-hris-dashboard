@extends('layouts.hr-app')

@section('title', $template ? 'Edit document template' : 'Create document template')
@section('breadcrumb', 'Document Templates')

@section('content')
<style>[x-cloak]{display:none!important}</style>

@php
    // Field metadata lookup, keyed by a safe field key (avoids @js inside attributes).
    $fieldMeta = [];
    foreach ($sections as $section) {
        foreach ($section->fields as $field) {
            $fk = $field->key ?: ('field_' . $field->id);
            $fieldMeta[$fk] = [
                'key' => $fk,
                'label' => $field->name,
                'section' => $section->name,
                'id' => $field->id,
                'type' => 'text',
            ];
        }
    }
    $fieldMeta['__signature'] = ['key' => '__signature', 'label' => 'Signature', 'section' => 'Signature', 'id' => '', 'type' => 'signature'];
    $fieldMeta['__initials'] = ['key' => '__initials', 'label' => 'Initials', 'section' => 'Signature', 'id' => '', 'type' => 'initials'];

    $entityOpts = $entities->map(fn ($e) => ['id' => (string) $e->id, 'name' => $e->name])->values();
    $deptOpts = $departments->map(fn ($d) => ['id' => (string) $d->id, 'name' => $d->name])->values();
    $signerEmployeeOpts = $employees->map(fn ($e) => [
        'value' => 'emp:' . $e->id,
        'label' => trim(($e->last_name ? $e->last_name . ', ' : '') . $e->first_name) . ($e->job_title ? ' — ' . $e->job_title : ''),
    ])->values();
@endphp

@php
    // When this template backs a company document, its name/file/scope were
    // already captured on the company-document form — so skip the redundant
    // "Edit template details" step and open straight on "Select signers".
    $backedByCompanyDoc = $template ? \App\Models\CompanyDocument::where('template_id', $template->id)->exists() : false;
@endphp
<script>
    window.__docTplExisting = @json($existing);
    window.__docTplFields = @json($fieldMeta);
    window.__docTplEntities = @json($entityOpts);
    window.__docTplDepartments = @json($deptOpts);
    window.__docTplSignerEmployees = @json($signerEmployeeOpts);
    window.__docTplHideDetails = @json($backedByCompanyDoc);
    window.__docTplOpenPlace = @json(request()->boolean('place'));
    window.__sigTemplates = @json(($signatureTemplates ?? collect())->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'image' => $s->image_data]));
</script>

<!-- PDF.js (legacy UMD build — most compatible) — powers the Step 4 placement editor -->
<script src="https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>
    if (window.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://unpkg.com/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';
    }
</script>

@php
    $steps = [
        1 => ['Edit template details', 'Name, file, scope'],
        2 => ['Select signers', 'Who signs & in what order'],
        3 => ['Select profile fields', 'Auto-filled employee data'],
        4 => ['Place profile fields', 'Position fields on the document'],
    ];
@endphp

<form method="POST"
      action="{{ $template ? route('document-templates.update', $template) : route('document-templates.store') }}"
      enctype="multipart/form-data"
      x-data="documentWizard()"
      x-init="$nextTick(() => { if (window.lucide) lucide.createIcons(); if (step === 4) initPlacement(); })"
      class="max-w-6xl mx-auto pb-10">
    @csrf
    @if($template) @method('PUT') @endif

    <!-- Header bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ $backedByCompanyDoc ? route('company-documents.admin') : route('document-templates.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700" title="Cancel">
                <i data-lucide="x" class="h-5 w-5"></i>
            </a>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $backedByCompanyDoc ? 'Signature setup' : ($template ? 'Edit document template' : 'Create document template') }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="stepLabel()"></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="prev()" x-show="step > minStep"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Previous
            </button>
            <button type="button" @click="next()" x-show="step < 4"
                    :disabled="!canAdvance()"
                    :class="!canAdvance() && 'opacity-50 cursor-not-allowed'"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                Next <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </button>
            <button type="submit"
                    :disabled="name.trim() === '' || (!isEdit && fileName === '')"
                    :class="(name.trim() === '' || (!isEdit && fileName === '')) && 'opacity-50 cursor-not-allowed'"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700">
                <i data-lucide="check" class="h-4 w-4"></i> Save template
            </button>
        </div>
    </div>

    @include('company-documents.partials.builder-steps', ['template' => $template, 'current' => 2])

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 mb-6 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 {{ $backedByCompanyDoc ? '' : 'lg:grid-cols-[230px_1fr]' }} gap-6">
        <!-- Step sidebar (hidden for company-document mode — single placement step) -->
        @unless($backedByCompanyDoc)
        <aside class="space-y-1.5">
            @foreach($steps as $i => $meta)
                @continue($backedByCompanyDoc && $i === 1)
                @php $badge = $backedByCompanyDoc ? $i - 1 : $i; @endphp
                <button type="button" @click="go({{ $i }})"
                        class="w-full text-left flex items-start gap-3 rounded-xl px-3.5 py-3 transition"
                        :class="step === {{ $i }} ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                          :class="step === {{ $i }} ? 'bg-brand-600 text-slate-900' : (step > {{ $i }} ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500 dark:bg-slate-700')">
                        <template x-if="step > {{ $i }}"><i data-lucide="check" class="h-3.5 w-3.5"></i></template>
                        <template x-if="step <= {{ $i }}"><span>{{ $badge }}</span></template>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold leading-tight" :class="step === {{ $i }} ? 'text-brand-700 dark:text-brand-400' : 'text-slate-700 dark:text-slate-200'">{{ $meta[0] }}</span>
                        <span class="block text-[11px] text-slate-400 leading-tight mt-0.5">{{ $meta[1] }}</span>
                    </span>
                </button>
            @endforeach
        </aside>
        @endunless

        <!-- Step panels -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 min-h-[420px]">

            {{-- ===================== STEP 1 ===================== --}}
            <div x-show="step === 1" class="space-y-6">
                @if($template)
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20 flex items-start gap-2">
                        <i data-lucide="info" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
                        <span>Editing this template creates a new version. Documents already sent with it are not affected.</span>
                    </div>
                @endif

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Template name <span class="text-rose-500">*</span></label>
                        <span class="text-[11px] font-medium" :class="name.length > 70 ? 'text-rose-500' : 'text-slate-400'" x-text="name.length + '/70'"></span>
                    </div>
                    <input type="text" name="name" x-model="name" maxlength="70" placeholder="e.g. Employment Contract"
                           class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>

                <!-- Description -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Description <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                        <span class="text-[11px] font-medium" :class="description.length > 140 ? 'text-rose-500' : 'text-slate-400'" x-text="description.length + '/140'"></span>
                    </div>
                    <textarea name="description" x-model="description" maxlength="140" rows="2" placeholder="A short note about what this template is for…"
                              class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                        File <span class="text-rose-500" x-show="!isEdit">*</span>
                        <span class="text-slate-400 normal-case font-medium">— PDF, DOC, DOCX, PPT, PPTX · max 10 MB</span>
                    </label>
                    @if($template && $template->file_name)
                        <p class="text-xs text-slate-500 mb-2 flex items-center gap-1.5">
                            <i data-lucide="paperclip" class="h-3.5 w-3.5"></i>
                            Current: <span class="font-semibold">{{ $template->file_name }}</span> (v{{ $template->version }})
                        </p>
                        <p class="text-[11px] text-amber-600 mb-2">A new version will be created if you replace the current file.</p>
                    @endif
                    <input type="file" name="file" @change="onFile($event)" accept=".pdf,.doc,.docx,.ppt,.pptx"
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    <p class="text-xs text-slate-500 mt-1.5" x-show="fileName" x-text="'Selected: ' + fileName"></p>
                </div>

                <!-- Tags -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Tags <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="(t, i) in tags" :key="i">
                            <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                <span x-text="t"></span>
                                <button type="button" @click="removeTag(i)" class="text-slate-400 hover:text-rose-500"><i data-lucide="x" class="h-3 w-3"></i></button>
                            </span>
                        </template>
                        <input type="text" x-model="newTag" @keydown.enter.prevent="addTag()"
                               placeholder="+ add tag, press Enter"
                               class="rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white w-44">
                    </div>
                </div>

                <!-- Scope -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2">
                    <!-- Entities multi-select -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Entities & locations</label>
                        <p class="text-[11px] text-slate-400 mb-2">Leave empty to apply to all employees.</p>
                        <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="relative">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
                                <span :class="entities.length ? 'text-slate-700 dark:text-white' : 'text-slate-400'"
                                      x-text="entities.length ? entities.length + ' selected' : 'Select entities…'"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-cloak x-transition.origin.top
                                 class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                                <input x-model="q" type="text" placeholder="Search…"
                                       class="w-full rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <div class="max-h-44 overflow-y-auto mt-2 space-y-0.5">
                                    <template x-for="opt in entityOptions.filter(o => o.name.toLowerCase().includes(q.toLowerCase()))" :key="opt.id">
                                        <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer text-sm text-slate-700 dark:text-slate-200">
                                            <input type="checkbox" name="entities[]" :value="opt.id" x-model="entities" class="rounded border-slate-300 text-brand-600">
                                            <span x-text="opt.name"></span>
                                        </label>
                                    </template>
                                    <p x-show="!entityOptions.length" class="text-xs text-slate-400 px-2 py-1">No entities configured.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2" x-show="entities.length" x-cloak>
                            <template x-for="id in entities" :key="id">
                                <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                    <span x-text="optName(entityOptions, id)"></span>
                                    <button type="button" @click="entities = removeId(entities, id)" class="text-brand-400 hover:text-rose-500 text-sm leading-none">&times;</button>
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Departments multi-select -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Departments</label>
                        <p class="text-[11px] text-slate-400 mb-2">Leave empty to apply to all departments.</p>
                        <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="relative">
                            <button type="button" @click="open = !open"
                                    class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
                                <span :class="departments.length ? 'text-slate-700 dark:text-white' : 'text-slate-400'"
                                      x-text="departments.length ? departments.length + ' selected' : 'Select departments…'"></span>
                                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-cloak x-transition.origin.top
                                 class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                                <input x-model="q" type="text" placeholder="Search…"
                                       class="w-full rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <div class="max-h-44 overflow-y-auto mt-2 space-y-0.5">
                                    <template x-for="opt in departmentOptions.filter(o => o.name.toLowerCase().includes(q.toLowerCase()))" :key="opt.id">
                                        <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer text-sm text-slate-700 dark:text-slate-200">
                                            <input type="checkbox" name="departments[]" :value="opt.id" x-model="departments" class="rounded border-slate-300 text-brand-600">
                                            <span x-text="opt.name"></span>
                                        </label>
                                    </template>
                                    <p x-show="!departmentOptions.length" class="text-xs text-slate-400 px-2 py-1">No departments configured.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5 mt-2" x-show="departments.length" x-cloak>
                            <template x-for="id in departments" :key="id">
                                <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                                    <span x-text="optName(departmentOptions, id)"></span>
                                    <button type="button" @click="departments = removeId(departments, id)" class="text-brand-400 hover:text-rose-500 text-sm leading-none">&times;</button>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== STEP 2 ===================== --}}
            <div x-show="step === 2" x-cloak class="space-y-5">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-white">Signers</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">The order sets the signing sequence — each signer is notified after the previous one signs.</p>
                </div>

                <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20">
                    If you add or remove signers, you'll need to redo the field placement step.
                </div>

                <div class="space-y-3">
                    <template x-for="(s, i) in signers" :key="i">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300" x-text="i + 1"></span>
                            <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="relative flex-1">
                                <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
                                    <span class="text-slate-700 dark:text-white truncate" x-text="signerLabel(s.choice)"></span>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition flex-shrink-0 ml-2" :class="open && 'rotate-180'"></i>
                                </button>
                                <div x-show="open" x-cloak x-transition.origin.top
                                     class="absolute z-30 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                                    <input x-model="q" type="text" placeholder="Search roles or employees…"
                                           class="w-full rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    <div class="max-h-56 overflow-y-auto mt-2">
                                        <div class="px-2 pt-1 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Roles</div>
                                        <template x-for="r in roleOptions.filter(o => o.label.toLowerCase().includes(q.toLowerCase()))" :key="r.value">
                                            <button type="button" @click="s.choice = r.value; open = false"
                                                    class="w-full text-left px-2 py-1.5 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                                    :class="s.choice === r.value ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-500/10 dark:text-brand-300' : 'text-slate-700 dark:text-slate-200'">
                                                <span x-text="r.label"></span>
                                            </button>
                                        </template>
                                        <div class="px-2 pt-2 pb-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-show="employeeOptions.length">Employees</div>
                                        <template x-for="e in employeeOptions.filter(o => o.label.toLowerCase().includes(q.toLowerCase()))" :key="e.value">
                                            <button type="button" @click="s.choice = e.value; open = false"
                                                    class="w-full text-left px-2 py-1.5 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                                    :class="s.choice === e.value ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-500/10 dark:text-brand-300' : 'text-slate-700 dark:text-slate-200'">
                                                <span x-text="e.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <button type="button" @click="removeSigner(i)" x-show="signers.length > 1"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-500 dark:hover:bg-rose-500/10 flex-shrink-0">
                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addSigner()" class="inline-flex items-center gap-1.5 text-sm font-bold text-brand-600 hover:text-brand-700">
                    <i data-lucide="plus" class="h-4 w-4"></i> Add signer
                </button>
            </div>

            {{-- ===================== STEP 3 ===================== --}}
            <div x-show="step === 3" x-cloak class="grid grid-cols-1 lg:grid-cols-[1fr_240px] gap-6">
                <!-- Field picker -->
                <div class="space-y-5">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Select profile fields to auto-fill</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Checked fields are filled from each employee's profile when the document is sent. <span x-text="`${selectionCount()} selected`" class="font-semibold text-brand-600"></span></p>
                    </div>

                    @forelse($sections as $section)
                        @if($section->fields->count())
                            <div class="rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700/60">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $section->name }}</span>
                                </div>
                                <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                    @foreach($section->fields as $field)
                                        @php $fkey = $field->key ?: ('field_' . $field->id); @endphp
                                        <div class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 cursor-pointer min-w-0">
                                                <input type="checkbox"
                                                       :checked="isSelected('{{ $fkey }}')"
                                                       @change="toggleField('{{ $fkey }}')"
                                                       class="rounded border-slate-300 text-brand-600 flex-shrink-0">
                                                <span class="truncate">{{ $field->name }}</span>
                                            </label>
                                            <select x-show="isSelected('{{ $fkey }}')" x-cloak
                                                    @change="if(selections['{{ $fkey }}']) selections['{{ $fkey }}'].assignee = $event.target.value"
                                                    :value="selections['{{ $fkey }}']?.assignee"
                                                    class="text-[11px] rounded-md border-slate-200 py-0.5 pr-6 dark:bg-slate-900 dark:border-slate-600 dark:text-white flex-shrink-0">
                                                <option value="sender">Sender</option>
                                                <option value="employee">Employee</option>
                                                <option value="hr_admin">HR admin</option>
                                                <option value="me_now">Me (now)</option>
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @empty
                        <p class="text-sm text-slate-400">No profile fields are configured yet.</p>
                    @endforelse
                </div>

                <!-- Right sidebar: assignment + signature blocks -->
                <aside class="space-y-4">
                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Assign new fields to</label>
                        <select x-model="activeAssignee" class="w-full rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="sender">Sender (auto-fill)</option>
                            <option value="employee">Employee</option>
                            <option value="hr_admin">HR admin</option>
                            <option value="me_now">Me (now)</option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-2">Use <span class="font-semibold">Sender</span> for data that should be filled automatically from the profile.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700 space-y-2">
                        <span class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider">Signature fields</span>
                        <button type="button" @click="toggleField('__signature')"
                                class="w-full flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                                :class="isSelected('__signature') ? 'border-brand-400 bg-brand-50 text-brand-700 dark:bg-brand-500/10' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300'">
                            <span class="flex items-center gap-2"><i data-lucide="pen-tool" class="h-4 w-4"></i> Signature</span>
                            <i data-lucide="check" class="h-4 w-4" x-show="isSelected('__signature')"></i>
                        </button>
                        <button type="button" @click="toggleField('__initials')"
                                class="w-full flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                                :class="isSelected('__initials') ? 'border-brand-400 bg-brand-50 text-brand-700 dark:bg-brand-500/10' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300'">
                            <span class="flex items-center gap-2"><i data-lucide="type" class="h-4 w-4"></i> Initials</span>
                            <i data-lucide="check" class="h-4 w-4" x-show="isSelected('__initials')"></i>
                        </button>
                    </div>
                </aside>
            </div>

            {{-- ===================== STEP 4: PLACEMENT EDITOR ===================== --}}
            <div x-show="step === 4" x-cloak class="flex flex-col lg:flex-row gap-5">
                <!-- Palette of fields to place -->
                <aside class="lg:w-60 shrink-0 space-y-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-white">Place fields</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><b>Drag a field onto the document</b> (or click it, then click a spot). Drag to move, drag the corner to resize.</p>
                    </div>

                    <!-- Token-without-fields warning (§ display-only tokens) -->
                    <p x-show="docTokenCount > 0 && placedCount() === 0" x-cloak class="flex items-start gap-1.5 rounded-lg bg-amber-50 border border-amber-200 px-2.5 py-1.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-300">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                        <span>This document uses <b x-text="docTokenCount"></b> <code>[token]</code> placeholder(s). These auto-fill everywhere in the signed PDF — you only need to drag a <b>Signature</b> field onto the signature line.</span>
                    </p>

                    <!-- No fields placed at all -->
                    <p x-show="pdfReady && placedCount() === 0" x-cloak class="flex items-start gap-1.5 rounded-lg bg-rose-50 border border-rose-200 px-2.5 py-1.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                        <span>No fields placed yet. <b>Drag a Signature field</b> onto the signature line (and any Name/Date fields onto their blanks) — otherwise the signer only gets a default signature box at the bottom of the last page.</span>
                    </p>
                    <!-- Placed but no signature field -->
                    <p x-show="pdfReady && placedCount() > 0 && !hasPlacedSignature()" x-cloak class="flex items-start gap-1.5 rounded-lg bg-amber-50 border border-amber-200 px-2.5 py-1.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/30 dark:text-amber-300">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5 mt-0.5 shrink-0"></i>
                        <span>No <b>Signature</b> field placed — add one so the signature lands where you want (otherwise it defaults to the bottom of the last page).</span>
                    </p>

                    <p x-show="placingKey" x-cloak class="flex items-center gap-1.5 rounded-lg bg-brand-50 border border-brand-200 px-2.5 py-1.5 text-[11px] font-bold text-brand-700 dark:bg-brand-500/10 dark:border-brand-500/30 dark:text-brand-300">
                        <i data-lucide="mouse-pointer-click" class="h-3.5 w-3.5"></i> Click on the document to drop it
                    </p>
                    <div class="space-y-1.5 max-h-[62vh] overflow-y-auto pr-1">
                        {{-- Signature group — always visible, its own prominent section --}}
                        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-500">Signature</p>
                        <template x-for="f in signatureFields()" :key="'sgfld' + f.key">
                            <button type="button" draggable="true"
                                    @dragstart="onDragStart(f, $event)" @dragend="dragField = null"
                                    @click="pick(f)" :disabled="!pdfReady"
                                    class="w-full flex items-center justify-between gap-2 rounded-lg border-2 px-3 py-2.5 text-xs font-bold transition cursor-grab active:cursor-grabbing"
                                    :class="placingKey === f.key ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-brand-200 bg-brand-50/40 text-brand-700 hover:bg-brand-50 dark:border-brand-500/30 dark:bg-brand-500/5 dark:text-brand-300'"
                                    :style="!pdfReady && 'opacity:0.5;cursor:not-allowed'">
                                <span class="flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="pen-tool" class="h-3.5 w-3.5 flex-shrink-0"></i>
                                    <span class="truncate" x-text="f.label"></span>
                                    <span x-show="f.placed" x-cloak class="text-[9px] font-bold text-emerald-600">· placed</span>
                                </span>
                                <i data-lucide="plus" class="h-3.5 w-3.5 flex-shrink-0"></i>
                            </button>
                        </template>

                        {{-- Text variables --}}
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-2">Important fields</p>
                        <template x-for="f in palette()" :key="'pal' + f.key">
                            <button type="button" draggable="true"
                                    @dragstart="onDragStart(f, $event)" @dragend="dragField = null"
                                    @click="pick(f)" :disabled="!pdfReady"
                                    class="w-full flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition cursor-grab active:cursor-grabbing"
                                    :class="placingKey === f.key ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50'"
                                    :style="!pdfReady && 'opacity:0.5;cursor:not-allowed'">
                                <span class="flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="grip-vertical" class="h-3 w-3 text-slate-300 flex-shrink-0"></i>
                                    <span class="h-2 w-2 rounded-full flex-shrink-0" :class="tokenDot(f.assignee)"></span>
                                    <span class="truncate" x-text="f.label"></span>
                                </span>
                                <i data-lucide="plus" class="h-3.5 w-3.5 text-slate-400 flex-shrink-0"></i>
                            </button>
                        </template>

                        {{-- Bottom group: every profile field, drag any onto the doc --}}
                        <p x-show="profilePalette().length > 0" class="text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-2">Profile fields</p>
                        <template x-for="f in profilePalette()" :key="'pf' + f.key">
                            <button type="button" draggable="true"
                                    @dragstart="onDragStart(f, $event)" @dragend="dragField = null"
                                    @click="pick(f)" :disabled="!pdfReady"
                                    class="w-full flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition cursor-grab active:cursor-grabbing"
                                    :class="placingKey === f.key ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50'"
                                    :style="!pdfReady && 'opacity:0.5;cursor:not-allowed'">
                                <span class="flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="grip-vertical" class="h-3 w-3 text-slate-300 flex-shrink-0"></i>
                                    <span class="h-2 w-2 rounded-full flex-shrink-0" :class="tokenDot(f.assignee)"></span>
                                    <span class="truncate" x-text="f.label"></span>
                                </span>
                                <i data-lucide="plus" class="h-3.5 w-3.5 text-slate-400 flex-shrink-0"></i>
                            </button>
                        </template>

                        {{-- Third group: saved signatures (email signatures) — stamped automatically --}}
                        <p x-show="signaturePalette().length > 0" class="text-[10px] font-bold uppercase tracking-wider text-slate-400 pt-2">Saved signatures</p>
                        <template x-for="f in signaturePalette()" :key="'sig' + f.key">
                            <button type="button" draggable="true"
                                    @dragstart="onDragStart(f, $event)" @dragend="dragField = null"
                                    @click="pick(f)" :disabled="!pdfReady"
                                    class="w-full flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs font-semibold transition cursor-grab active:cursor-grabbing"
                                    :class="placingKey === f.key ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50'"
                                    :style="!pdfReady && 'opacity:0.5;cursor:not-allowed'">
                                <span class="flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="grip-vertical" class="h-3 w-3 text-slate-300 flex-shrink-0"></i>
                                    <img :src="f.sigImage" class="h-4 w-8 object-contain flex-shrink-0" alt="">
                                    <span class="truncate" x-text="f.label"></span>
                                </span>
                                <i data-lucide="plus" class="h-3.5 w-3.5 text-slate-400 flex-shrink-0"></i>
                            </button>
                        </template>

                        <p x-show="palette().length === 0 && profilePalette().length === 0 && signaturePalette().length === 0" class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                            <i data-lucide="check-circle" class="h-3.5 w-3.5"></i> All fields placed
                        </p>
                    </div>
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-1.5 text-[11px] text-slate-400">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-pink-500"></span> Sender (auto-fill)</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-teal-500"></span> Employee</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-orange-500"></span> HR admin</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-slate-500"></span> Me (now)</span>
                    </div>
                </aside>

                <!-- Document canvas -->
                <div class="flex-1 min-w-0" x-ref="pdfWrap">
                    <div x-show="pdfLoading" class="py-20 text-center text-sm text-slate-400">
                        <i data-lucide="loader" class="h-5 w-5 mx-auto mb-2 animate-spin"></i> Rendering document…
                    </div>
                    <div x-show="!pdfReady && !pdfLoading" class="py-20 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="file-text" class="h-7 w-7"></i></div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto" x-text="pdfError"></p>
                    </div>
                    <div x-show="pdfReady" class="space-y-4 overflow-x-auto">
                        <template x-for="pg in pages" :key="'pg' + pg.num">
                            <div class="relative mx-auto rounded-lg border shadow-sm bg-white dark:border-slate-700"
                                 :style="`width:${pg.width}px; height:${pg.height}px` + (placingKey ? ';cursor:crosshair' : '')" :data-page="pg.num"
                                 :class="dragField ? 'border-brand-400 ring-2 ring-brand-200' : 'border-slate-200'"
                                 @click="onPageClick($event, pg)"
                                 @dragover.prevent @drop.prevent="onDrop($event, pg)">
                                <canvas :id="'pdfcanvas-' + pg.num" class="block rounded-lg pointer-events-none"></canvas>
                                <template x-for="f in placedOnPage(pg.num)" :key="'tok' + f.key">
                                    <div class="absolute flex items-center justify-between gap-1 rounded px-1.5 text-[10px] font-bold text-white shadow cursor-move select-none ring-1 ring-white/40"
                                         :class="tokenColor(f.assignee)" :style="tokenStyle(f, pg)"
                                         @click.stop @pointerdown="startDrag($event, f, pg)">
                                        <template x-if="sigImgFor(f)"><img :src="sigImgFor(f)" class="absolute inset-0 h-full w-full object-contain p-0.5 pointer-events-none bg-white/70 rounded" alt=""></template>
                                        <span class="truncate pointer-events-none relative" x-text="f.label" :class="sigImgFor(f) && 'opacity-0'"></span>
                                        <button type="button" @click.stop="unplace(f.key)" @pointerdown.stop class="leading-none hover:text-rose-200 shrink-0">&times;</button>
                                        <!-- resize handle -->
                                        <span @pointerdown.stop="startResize($event, f, pg)" @click.stop
                                              class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-sm bg-white/80 ring-1 ring-slate-400 cursor-nwse-resize"
                                              style="transform:translate(35%,35%)"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden inputs that submit dynamic Alpine state -->
    <div class="hidden" aria-hidden="true">
        <template x-for="(t, i) in tags" :key="'tag' + i">
            <input type="hidden" name="tags[]" :value="t">
        </template>
        <template x-for="(s, i) in signers" :key="'sg' + i">
            <span>
                <input type="hidden" :name="`signers[${i}][signer_type]`" :value="s.choice.startsWith('emp:') ? 'employee' : 'role'">
                <input type="hidden" :name="`signers[${i}][role]`" :value="s.choice.startsWith('role:') ? s.choice.slice(5) : ''">
                <input type="hidden" :name="`signers[${i}][employee_id]`" :value="s.choice.startsWith('emp:') ? s.choice.slice(4) : ''">
            </span>
        </template>
        <template x-for="(f, i) in selectionList()" :key="'fl' + f.key">
            <span>
                <input type="hidden" :name="`fields[${i}][field_key]`" :value="f.key">
                <input type="hidden" :name="`fields[${i}][label]`" :value="f.label">
                <input type="hidden" :name="`fields[${i}][section]`" :value="f.section">
                <input type="hidden" :name="`fields[${i}][field_type]`" :value="f.type">
                <input type="hidden" :name="`fields[${i}][assignee]`" :value="f.assignee">
                <input type="hidden" :name="`fields[${i}][profile_field_id]`" :value="f.id">
                <input type="hidden" :name="`fields[${i}][signature_template_id]`" :value="f.sigId || ''">
                <input type="hidden" :name="`fields[${i}][page]`" :value="f.placement ? f.placement.page : ''">
                <input type="hidden" :name="`fields[${i}][pos_x]`" :value="f.placement ? f.placement.x : ''">
                <input type="hidden" :name="`fields[${i}][pos_y]`" :value="f.placement ? f.placement.y : ''">
                <input type="hidden" :name="`fields[${i}][width]`" :value="f.placement ? f.placement.w : ''">
                <input type="hidden" :name="`fields[${i}][height]`" :value="f.placement ? f.placement.h : ''">
            </span>
        </template>
    </div>
</form>

<script>
    function documentWizard() {
        const ex = window.__docTplExisting || null;
        const hideDetails = !!window.__docTplHideDetails;
        // Backed by a company document → the flow is a SINGLE placement step
        // (upload happened in step 1; signers/access/send are separate pages).
        const companyMode = hideDetails;
        // PDF.js objects kept OUTSIDE Alpine reactive state — proxying them breaks
        // their private (#) class fields, which makes render() throw.
        let __pdfPages = [];
        return {
            step: companyMode ? 4 : 1,
            hideDetails,
            companyMode,
            minStep: companyMode ? 4 : 1,
            placingKey: null,
            placingItem: null,
            dragField: null,
            docTokenCount: 0,
            // Always-available field palette (Adobe-style) — drop any of these on
            // the document; values resolve from the signer's profile on signing.
            fieldCatalog: [
                { key: 'full_name',   label: 'Full name',  type: 'text',      w: 0.30, h: 0.03 },
                { key: 'job',         label: 'Job title',  type: 'text',      w: 0.26, h: 0.03 },
                { key: 'date',        label: 'Date',       type: 'text',      w: 0.20, h: 0.03 },
                { key: 'salary',      label: 'Salary',     type: 'text',      w: 0.22, h: 0.03 },
                { key: 'department',  label: 'Department', type: 'text',      w: 0.26, h: 0.03 },
                { key: 'email',       label: 'Email',      type: 'text',      w: 0.32, h: 0.03 },
                { key: 'cnic_number', label: 'CNIC',       type: 'text',      w: 0.26, h: 0.03 },
                { key: '__signature', label: 'Signature',  type: 'signature', w: 0.22, h: 0.055 },
                { key: '__initials',  label: 'Initials',   type: 'initials',  w: 0.10, h: 0.05 },
            ],
            // Every profile field — shown as the second palette group so the admin
            // can drag any of them straight onto the document (no separate step).
            allProfileFields: Object.values(window.__docTplFields || {})
                .filter(f => f.key !== '__signature' && f.key !== '__initials'),
            // Saved signatures (email signatures) — third palette group; stamped automatically.
            savedSignatures: window.__sigTemplates || [],
            isEdit: !!ex,
            name: ex?.name || '',
            description: ex?.description || '',
            tags: ex?.tags ? [...ex.tags] : [],
            newTag: '',
            entities: ex?.entities ? [...ex.entities] : [],
            departments: ex?.departments ? [...ex.departments] : [],
            signers: (ex?.signers && ex.signers.length)
                ? ex.signers.map(s => ({ choice: s.signer_type === 'employee' ? ('emp:' + s.employee_id) : ('role:' + (s.role || 'employee')) }))
                : [{ choice: 'role:employee' }],
            selections: ex?.selections ? JSON.parse(JSON.stringify(ex.selections)) : {},
            activeAssignee: 'sender',
            fileName: ex?.fileName || '',
            // Placement editor state
            fileObject: null,
            fileObjectUrl: null,
            localIsPdf: false,
            pages: [],
            pdfReady: false,
            pdfLoading: false,
            pdfError: '',
            entityOptions: window.__docTplEntities || [],
            departmentOptions: window.__docTplDepartments || [],
            roleOptions: [
                { value: 'role:me_now', label: 'Me (now)' },
                { value: 'role:sender', label: 'Sender' },
                { value: 'role:employee', label: 'Employee' },
                { value: 'role:hr_admin', label: 'HR admin (requesting eSignature)' },
                { value: 'role:line_manager', label: 'Line manager' },
            ],
            employeeOptions: window.__docTplSignerEmployees || [],

            signerLabel(choice) {
                const o = this.roleOptions.concat(this.employeeOptions).find(o => o.value === choice);
                return o ? o.label : 'Select signer…';
            },

            optName(options, id) {
                const o = options.find(o => String(o.id) === String(id));
                return o ? o.name : id;
            },
            removeId(arr, id) { return arr.filter(x => String(x) !== String(id)); },

            stepTitle() {
                return ['', 'Edit template details', 'Select signers', 'Select profile fields', 'Place profile fields'][this.step];
            },
            stepLabel() {
                const total = this.hideDetails ? 3 : 4;
                const shown = this.hideDetails ? this.step - 1 : this.step;
                return `Step ${shown} of ${total} — ` + this.stepTitle();
            },

            addTag() { const t = this.newTag.trim(); if (t && !this.tags.includes(t)) this.tags.push(t); this.newTag = ''; },
            removeTag(i) { this.tags.splice(i, 1); },

            addSigner() { this.signers.push({ choice: 'role:employee' }); },
            removeSigner(i) { if (this.signers.length > 1) this.signers.splice(i, 1); },

            toggleField(key) {
                if (this.selections[key]) { delete this.selections[key]; return; }
                const meta = (window.__docTplFields || {})[key];
                if (!meta) return;
                this.selections[key] = {
                    key: meta.key, label: meta.label, section: meta.section,
                    id: meta.id || '', type: meta.type || 'text', assignee: this.activeAssignee,
                };
            },
            isSelected(key) { return !!this.selections[key]; },
            selectionList() { return Object.values(this.selections); },
            selectionCount() { return Object.keys(this.selections).length; },

            go(s) {
                if (s < this.minStep) return;
                this.step = s;
                this.$nextTick(() => {
                    if (window.lucide) lucide.createIcons();
                    if (s === 4) this.initPlacement();
                });
            },
            next() { if (this.step < 4 && this.canAdvance()) this.go(this.step + 1); },
            prev() { if (this.step > this.minStep) this.go(this.step - 1); },
            canAdvance() {
                if (this.step === 1) return this.name.trim() !== '' && (this.isEdit || this.fileName !== '');
                return true;
            },
            onFile(e) {
                const file = e.target.files[0] || null;
                this.fileName = file ? file.name : '';
                if (this.fileObjectUrl) { URL.revokeObjectURL(this.fileObjectUrl); this.fileObjectUrl = null; }
                this.fileObject = file;
                this.localIsPdf = !!file && /\.pdf$/i.test(file.name);
                this.fileObjectUrl = (file && this.localIsPdf) ? URL.createObjectURL(file) : null;
                this.pdfReady = false; this.pages = [];
            },

            // ---------- Step 4: PDF placement editor ----------
            placementSrc() { return this.fileObjectUrl || (ex && ex.fileUrl) || null; },
            placementIsPdf() { return this.fileObject ? this.localIsPdf : !!(ex && ex.isPdf); },

            initPlacement() {
                const src = this.placementSrc();
                if (!this.placementIsPdf() || !src) {
                    this.pdfReady = false;
                    this.pdfError = (this.fileName && !this.placementIsPdf())
                        ? 'Placement is available for PDF templates only — your selected fields are still saved.'
                        : 'Upload a PDF file in Step 1 to position fields on the document.';
                    return;
                }
                if (this.pdfReady && this.pages.length) return; // already rendered
                this.loadPdf(src);
            },

            async loadPdf(src) {
                this.pdfLoading = true; this.pdfError = ''; this.pages = []; this.pdfReady = false;
                __pdfPages = [];
                try {
                    const lib = window.pdfjsLib;
                    if (!lib) { this.pdfError = 'PDF viewer failed to load. Check your connection and retry.'; this.pdfLoading = false; return; }
                    const doc = await lib.getDocument(src).promise;
                    const wrapW = (this.$refs.pdfWrap && this.$refs.pdfWrap.clientWidth) || 700;
                    const targetW = Math.min(720, wrapW || 700);
                    const meta = [];
                    for (let n = 1; n <= doc.numPages; n++) {
                        const page = await doc.getPage(n);
                        const vp = page.getViewport({ scale: targetW / page.getViewport({ scale: 1 }).width });
                        __pdfPages.push({ page, vp });
                        meta.push({ num: n, width: Math.round(vp.width), height: Math.round(vp.height) });
                    }
                    this.pages = meta;
                    this.pdfReady = true;
                    this.pdfLoading = false;
                    await this.$nextTick();
                    for (let i = 0; i < meta.length; i++) {
                        const canvas = document.getElementById('pdfcanvas-' + meta[i].num);
                        if (!canvas) continue;
                        canvas.width = meta[i].width; canvas.height = meta[i].height;
                        await __pdfPages[i].page.render({ canvasContext: canvas.getContext('2d'), viewport: __pdfPages[i].vp }).promise;
                    }
                    // Count [tokens] in the text so we can warn if they're used
                    // without placed fields (tokens are display-only, not flattened).
                    this.docTokenCount = 0;
                    for (let i = 0; i < __pdfPages.length; i++) {
                        try {
                            const tc = await __pdfPages[i].page.getTextContent();
                            tc.items.forEach((it) => { const m = (it.str || '').match(/\[[^\]\r\n]{1,40}\]/g); if (m) this.docTokenCount += m.length; });
                        } catch (e) { /* ignore */ }
                    }
                } catch (err) {
                    this.pdfReady = false; this.pdfLoading = false;
                    this.pdfError = 'Could not render this PDF (' + (err && err.message ? err.message : err) + '). The file is still saved.';
                }
            },

            placedCount() { return this.selectionList().filter(f => f.placement).length; },
            hasPlacedSignature() { return this.selectionList().some(f => f.placement && (f.type === 'signature' || f.type === 'initials')); },

            placedOnPage(num) { return this.selectionList().filter(f => f.placement && f.placement.page === num); },
            unplacedFields() { return this.selectionList().filter(f => !f.placement); },

            // Merged, always-available palette: catalog fields not yet placed,
            // plus any step-2 selections not in the catalog and not yet placed.
            palette() {
                const placed = new Set(this.selectionList().filter(f => f.placement).map(f => f.key));
                const out = [];
                this.fieldCatalog.forEach(c => {
                    if (c.key === '__signature' || c.key === '__initials') return; // shown in their own group
                    if (placed.has(c.key)) return;
                    const sel = this.selections[c.key];
                    out.push({ ...c, assignee: (sel && sel.assignee) || 'employee' });
                });
                this.selectionList().forEach(f => {
                    if (f.placement) return;
                    if (this.fieldCatalog.some(c => c.key === f.key)) return;
                    if (out.some(o => o.key === f.key)) return;
                    out.push({ key: f.key, label: f.label, type: f.type, assignee: f.assignee || 'employee', w: 0.24, h: 0.03 });
                });
                return out;
            },
            // Signature & Initials — their OWN always-visible group so they can
            // never scroll off or get filtered out of the text-field list.
            signatureFields() {
                return this.fieldCatalog
                    .filter(c => c.key === '__signature' || c.key === '__initials')
                    .map(c => ({ ...c, assignee: 'employee', placed: !!(this.selections[c.key] && this.selections[c.key].placement) }));
            },
            // Second palette group: all profile fields not already in the top
            // catalog and not yet placed. Drag any onto the document.
            profilePalette() {
                const placed = new Set(this.selectionList().filter(f => f.placement).map(f => f.key));
                const catalogKeys = new Set(this.fieldCatalog.map(c => c.key));
                const paletteKeys = new Set(this.palette().map(p => p.key));
                return this.allProfileFields
                    .filter(f => !placed.has(f.key) && !catalogKeys.has(f.key) && !paletteKeys.has(f.key))
                    .map(f => ({ key: f.key, label: f.label, type: 'text', assignee: 'employee', id: f.id || '', w: 0.24, h: 0.03 }));
            },
            // Resolve a placed saved-signature's image (from the drop, or by id in edit mode).
            sigImgFor(f) {
                if (f.type !== 'saved_signature') return null;
                if (f.sigImage) return f.sigImage;
                const s = this.savedSignatures.find(s => String(s.id) === String(f.sigId));
                return s ? s.image : null;
            },
            // Third palette group: saved signatures (stamped automatically).
            signaturePalette() {
                const placed = new Set(this.selectionList().filter(f => f.placement).map(f => f.key));
                return this.savedSignatures
                    .map(s => ({ key: 'sig:' + s.id, label: s.name, type: 'saved_signature', assignee: 'sender', sigId: s.id, sigImage: s.image, w: 0.22, h: 0.06 }))
                    .filter(s => !placed.has(s.key));
            },
            ensureSelection(item) {
                if (!this.selections[item.key]) {
                    this.selections[item.key] = { key: item.key, label: item.label, section: 'Placed', id: item.id || '', type: item.type, assignee: item.assignee || 'employee', sigId: item.sigId || '', sigImage: item.sigImage || '' };
                }
                return this.selections[item.key];
            },
            dropAt(item, e, pg) {
                const f = this.ensureSelection(item);
                const rect = e.currentTarget.getBoundingClientRect();
                const isSig = (item.type === 'signature' || item.type === 'initials');
                const w = item.w || (isSig ? 0.22 : 0.24);
                const h = item.h || (isSig ? 0.05 : 0.03);
                const x = (e.clientX - rect.left) / rect.width - w / 2;
                const y = (e.clientY - rect.top) / rect.height - h / 2;
                f.placement = { page: pg.num, x: Math.max(0, Math.min(1 - w, x)), y: Math.max(0, Math.min(1 - h, y)), w, h };
                this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
            },
            // Click a field, then click on the document to drop it there.
            pick(item) {
                if (!this.pdfReady) return;
                if (this.placingKey === item.key) { this.placingKey = null; this.placingItem = null; return; }
                this.placingKey = item.key; this.placingItem = item;
            },
            onPageClick(e, pg) {
                if (!this.placingKey) return;
                const item = this.placingItem || this.selections[this.placingKey];
                if (!item) { this.placingKey = null; return; }
                this.dropAt(item, e, pg);
                this.placingKey = null; this.placingItem = null;
            },
            // Drag a field from the palette onto the document. Setting drag data
            // is REQUIRED for the drop to fire in some browsers (e.g. Firefox).
            onDragStart(item, e) {
                this.dragField = item;
                if (e && e.dataTransfer) {
                    try { e.dataTransfer.setData('text/plain', item.key || ''); e.dataTransfer.effectAllowed = 'copy'; } catch (_) {}
                }
            },
            onDrop(e, pg) {
                const item = this.dragField; this.dragField = null;
                if (!item || !this.pdfReady) return;
                this.dropAt(item, e, pg);
                this.placingKey = null; this.placingItem = null;
            },
            unplace(key) { if (this.selections[key]) this.selections[key].placement = null; },

            tokenStyle(f, pg) {
                const x = f.placement.x * pg.width, y = f.placement.y * pg.height;
                const w = f.placement.w * pg.width, h = (f.placement.h || 0.03) * pg.height;
                return `left:${x}px; top:${y}px; width:${w}px; height:${h}px; min-height:16px;`;
            },
            tokenColor(a) {
                return { employee: 'bg-teal-500/90', sender: 'bg-pink-500/90', hr_admin: 'bg-orange-500/90', me_now: 'bg-slate-500/90' }[a] || 'bg-slate-500/90';
            },
            tokenDot(a) {
                return { employee: 'bg-teal-500', sender: 'bg-pink-500', hr_admin: 'bg-orange-500', me_now: 'bg-slate-500' }[a] || 'bg-slate-500';
            },

            startDrag(e, f, pg) {
                e.preventDefault();
                const container = e.currentTarget.closest('[data-page]');
                if (!container) return;
                const rect = container.getBoundingClientRect();
                const tok = e.currentTarget.getBoundingClientRect();
                const offX = e.clientX - tok.left, offY = e.clientY - tok.top;
                const move = (ev) => {
                    let nx = (ev.clientX - rect.left - offX) / rect.width;
                    let ny = (ev.clientY - rect.top - offY) / rect.height;
                    f.placement.x = Math.max(0, Math.min(1 - f.placement.w, nx));
                    f.placement.y = Math.max(0, Math.min(0.99, ny));
                };
                const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },

            // Drag the bottom-right corner to resize the field box.
            startResize(e, f, pg) {
                e.preventDefault();
                const container = e.currentTarget.closest('[data-page]');
                if (!container) return;
                const rect = container.getBoundingClientRect();
                const move = (ev) => {
                    const nw = ((ev.clientX - rect.left) / rect.width) - f.placement.x;
                    const nh = ((ev.clientY - rect.top) / rect.height) - f.placement.y;
                    f.placement.w = Math.max(0.05, Math.min(1 - f.placement.x, nw));
                    f.placement.h = Math.max(0.015, Math.min(1 - f.placement.y, nh));
                };
                const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            },
        };
    }
</script>
@endsection
