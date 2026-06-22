@extends('layouts.hr-app')

@section('title', ($policy->exists ? 'Edit' : 'New') . ' Policy')
@section('breadcrumb', 'Company Policies')

@php $editing = $policy->exists; @endphp

@section('content')
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    [x-cloak]{display:none!important}
    .ql-editor { min-height: 220px; font-size: 14px; }
    .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: #cbd5e1; }
    .ql-toolbar.ql-snow { border-radius: 10px 10px 0 0; } .ql-container.ql-snow { border-radius: 0 0 10px 10px; }
</style>

<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('company-policies.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All policies</a>

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $editing ? route('company-policies.update', $policy) : route('company-policies.store') }}" enctype="multipart/form-data"
          x-data="{ ack: {{ $policy->requires_acknowledgment ? 'true' : 'false' }} }" @submit="document.getElementById('content').value = window.__quill ? window.__quill.root.innerHTML : ''">
        @csrf
        @if($editing) @method('PUT') @endif

        <!-- Basic info -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Policy details</h2>
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title <span class="text-rose-500">*</span></label><input type="text" name="title" value="{{ old('title', $policy->title) }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach(['hr'=>'HR','it'=>'IT','finance'=>'Finance','legal'=>'Legal','health_safety'=>'Health & Safety','general'=>'General'] as $v=>$l)<option value="{{ $v }}" @selected(old('category',$policy->category)===$v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Version</label><input type="text" name="version" value="{{ old('version', $policy->version ?: '1.0') }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Status</label>
                    <select name="status" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach(['draft'=>'Draft','active'=>'Active','archived'=>'Archived'] as $v=>$l)<option value="{{ $v }}" @selected(old('status',$policy->status)===$v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Effective date</label><input type="date" name="effective_date" value="{{ old('effective_date', optional($policy->effective_date)->format('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Review date</label><input type="date" name="review_date" value="{{ old('review_date', optional($policy->review_date)->format('Y-m-d')) }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Ack. deadline (days)</label><input type="number" name="acknowledgment_deadline" value="{{ old('acknowledgment_deadline', $policy->acknowledgment_deadline) }}" min="0" max="365" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
            </div>
            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Short description</label><textarea name="description" rows="2" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none">{{ old('description', $policy->description) }}</textarea></div>
        </div>

        <!-- Content -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Policy content</h2>
            <div id="editor"></div>
            <textarea id="content" name="content" class="hidden">{{ old('content', $policy->content) }}</textarea>
        </div>

        <!-- Document -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Document <span class="text-slate-400 font-medium normal-case">(optional — PDF, DOC, DOCX, max 20 MB)</span></h2>
            @if($policy->document_file)
                <p class="text-xs text-slate-500 flex items-center gap-2"><i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Current: <a href="{{ route('policies.download', $policy) }}" class="font-semibold text-brand-600 hover:underline">{{ $policy->document_filename }}</a> ({{ $policy->document_size_label }}). Upload to replace.</p>
            @endif
            <input type="file" name="document" accept=".pdf,.doc,.docx" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
        </div>

        <!-- Settings -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Settings</h2>
            <label class="flex items-center justify-between cursor-pointer">
                <span class="text-sm font-semibold text-slate-800 dark:text-white">Requires acknowledgment</span>
                <input type="checkbox" name="requires_acknowledgment" value="1" x-model="ack" class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
            <label class="flex items-center justify-between cursor-pointer" x-show="ack" x-cloak>
                <span class="text-sm font-semibold text-slate-800 dark:text-white">Requires signature <span class="text-slate-400 font-normal">(drawn or typed)</span></span>
                <input type="checkbox" name="requires_signature" value="1" @checked($policy->requires_signature) class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('company-policies.index') }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</a>
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">{{ $editing ? 'Save changes' : 'Create policy' }}</button>
        </div>
    </form>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    (function () {
        window.__quill = new Quill('#editor', {
            theme: 'snow',
            modules: { toolbar: [[{ header: [1, 2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] },
        });
        var initial = document.getElementById('content').value;
        if (initial) { window.__quill.clipboard.dangerouslyPasteHTML(initial); }
    })();
</script>
@endsection
