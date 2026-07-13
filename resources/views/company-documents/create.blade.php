@extends('layouts.hr-app')

@section('title', ($document->exists ? 'Edit' : 'Add') . ' Document')
@section('breadcrumb', 'Company Documents')

@php
    $editing = $document->exists;
    $selectedDepts = $editing ? $document->accessRecords->where('access_type', 'department')->pluck('access_id')->map(fn($i)=>(int)$i)->all() : [];
    $selectedUsers = $editing ? $document->accessRecords->where('access_type', 'user')->pluck('access_id')->map(fn($i)=>(int)$i)->all() : [];
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div class="max-w-3xl mx-auto space-y-6"
     x-data="{
        access: '{{ old('access_level', $document->access_level ?? 'company_wide') }}',
        ack: {{ old('requires_acknowledgment', $document->requires_acknowledgment) ? 'true' : 'false' }},
        sign: {{ old('requires_signature', $document->requires_signature ?? false) ? 'true' : 'false' }},
        fileName: '', fileSize: '',
        userSearch: '',
        selectedUsers: @js(old('users', $selectedUsers)),
        onFile(e) { const f = e.target.files[0]; if (!f) { this.fileName=''; return; } this.fileName = f.name; this.fileSize = (f.size/1048576).toFixed(2) + ' MB'; },
     }">
    <a href="{{ route('company-documents.admin') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All documents</a>

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($editing && $document->requires_signature && $document->template)
        @php $tplSignerCount = $document->template->signers->count(); @endphp
        <div class="rounded-2xl border border-brand-200 bg-brand-50/50 shadow-sm p-6 dark:border-brand-500/30 dark:bg-brand-500/10">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-700 dark:bg-brand-500/20 shrink-0"><i data-lucide="file-signature" class="h-5 w-5"></i></span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Signature setup</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        @if($tplSignerCount === 0)
                            No signers yet — set up signers &amp; fields before you can send this for signature.
                        @else
                            {{ $tplSignerCount }} {{ \Illuminate\Support\Str::plural('signer', $tplSignerCount) }} configured. You can preview or send it out.
                        @endif
                    </p>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <a href="{{ route('document-templates.edit', $document->template) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="users" class="h-4 w-4"></i> Set up signers &amp; fields</a>
                        <a href="{{ route('document-templates.preview', $document->template) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="eye" class="h-4 w-4"></i> Preview</a>
                        @if($tplSignerCount > 0)
                            <a href="{{ route('document-templates.send-form', $document->template) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="send" class="h-4 w-4"></i> Send for signature</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('company-documents.update', $document) : route('company-documents.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <!-- Details -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Details</h2>
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title <span class="text-rose-500">*</span></label><input type="text" name="title" value="{{ old('title', $document->title) }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <div x-data="{ adding: false, newName: '' }">
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Category <span class="text-rose-500">*</span></label>
                    <button type="button" @click="adding = !adding" class="text-xs font-bold text-brand-600 hover:underline">+ New category</button>
                </div>
                <select name="category_id" x-ref="catSelect" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(old('category_id', $document->category_id)==$cat->id)>{{ $cat->name }}</option>@endforeach
                </select>
                <div x-show="adding" x-cloak class="mt-2 flex items-center gap-2">
                    <input type="text" x-model="newName" placeholder="Category name" class="flex-1 rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <button type="button" @click="
                        if (!newName.trim()) return;
                        fetch('{{ route('document-categories.store') }}', { method:'POST', headers:{ 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json', 'Content-Type':'application/json' }, body: JSON.stringify({ name: newName }) })
                          .then(r => r.json()).then(c => { const o=new Option(c.name, c.id, true, true); $refs.catSelect.add(o); adding=false; newName=''; });
                    " class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700">Add</button>
                </div>
            </div>
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label><textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none">{{ old('description', $document->description) }}</textarea></div>
        </div>

        <!-- File -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">File @if($editing)<span class="text-slate-400 font-medium normal-case">— leave empty to keep current</span>@endif</h2>
            @if($editing && $document->file_name)
                <p class="text-xs text-slate-500 flex items-center gap-2"><i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Current: <span class="font-semibold">{{ $document->file_name }}</span> ({{ $document->file_size_label }})</p>
            @endif
            <label class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 py-8 cursor-pointer hover:border-brand-400 transition">
                <i data-lucide="upload-cloud" class="h-8 w-8 text-slate-400"></i>
                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300" x-text="fileName || 'Click to choose a file'"></span>
                <span class="text-xs text-slate-400" x-text="fileSize || 'PDF, DOC, XLS, PPT, image or ZIP — up to 50 MB'"></span>
                <input type="file" name="file" class="hidden" @change="onFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.zip" {{ $editing ? '' : 'required' }}>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Version</label><input type="text" name="version" value="{{ old('version', $document->version ?: '1.0') }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div class="sm:col-span-2"><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Version notes</label><input type="text" name="version_notes" value="{{ old('version_notes', $document->version_notes) }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            </div>
        </div>

        <!-- Access -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Who can access this?</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                @foreach([['company_wide','Company-wide','All employees','users'],['department','Department','Selected departments','building-2'],['specific_users','Specific employees','Hand-pick people','user-check']] as [$val,$label,$desc,$icon])
                    <label class="cursor-pointer rounded-xl border p-3 transition" :class="access === '{{ $val }}' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                        <input type="radio" name="access_level" value="{{ $val }}" x-model="access" class="sr-only">
                        <div class="flex items-center gap-2 text-slate-800 dark:text-white"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i><span class="text-sm font-bold">{{ $label }}</span></div>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $desc }}</p>
                    </label>
                @endforeach
            </div>

            <!-- Departments -->
            <div x-show="access === 'department'" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Departments</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                    @foreach($departments as $d)
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input type="checkbox" name="departments[]" value="{{ $d->id }}" @checked(in_array($d->id, old('departments', $selectedDepts))) class="rounded border-slate-300 text-brand-600">
                            {{ $d->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Specific users -->
            <div x-show="access === 'specific_users'" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Employees</label>
                <input type="text" x-model="userSearch" placeholder="Search employees…" class="w-full rounded-xl border-slate-300 text-sm mb-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                    @foreach($users as $u)
                        @php $name = trim($u->first_name.' '.$u->last_name); @endphp
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200" data-name="{{ Str::lower($name) }}" x-show="userSearch === '' || $el.dataset.name.includes(userSearch.toLowerCase())">
                            <input type="checkbox" name="users[]" value="{{ $u->id }}" x-model.number="selectedUsers" class="rounded border-slate-300 text-brand-600">
                            {{ $name }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-1"><span x-text="selectedUsers.length"></span> selected</p>
            </div>
        </div>

        <!-- Settings -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Settings</h2>
            <label class="flex items-center justify-between cursor-pointer">
                <span class="text-sm font-semibold text-slate-800 dark:text-white">Requires acknowledgment</span>
                <input type="checkbox" name="requires_acknowledgment" value="1" x-model="ack" class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
            <div class="border-t border-slate-100 dark:border-slate-700/60 pt-4">
                <label class="flex items-center justify-between cursor-pointer">
                    <span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-white block">Requires signature (send to employees to e-sign)</span>
                        <span class="text-xs text-slate-400">Turn on to add signers, place fields and send this document for signature. PDF only.</span>
                    </span>
                    <input type="checkbox" name="requires_signature" value="1" x-model="sign" class="rounded border-slate-300 text-brand-600 h-5 w-5 shrink-0">
                </label>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Expires at <span class="text-slate-400 font-normal normal-case">(optional)</span></label><input type="date" name="expires_at" value="{{ old('expires_at', optional($document->expires_at)->format('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <label class="flex items-center gap-2 cursor-pointer pt-6">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $document->is_active ?? true)) class="rounded border-slate-300 text-brand-600 h-5 w-5">
                    <span class="text-sm font-semibold text-slate-800 dark:text-white">Active (visible to employees)</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('company-documents.admin') }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</a>
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">{{ $editing ? 'Save changes' : 'Upload document' }}</button>
        </div>
    </form>
</div>
@endsection
