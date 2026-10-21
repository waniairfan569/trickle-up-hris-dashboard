@extends('layouts.hr-app')

@section('title', 'Publish to staff')
@section('breadcrumb', 'Documents')

@section('content')
@php
    $inp = 'w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white';
    $lbl = 'block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5';
    $default = $canWrite ? 'write' : 'upload';
@endphp

{{-- One form, posting to whichever existing endpoint matches the choice, so the
     real validation and file handling are never duplicated here. --}}
<div class="max-w-3xl mx-auto space-y-5"
     x-data="{
        mode: '{{ $default }}',
        get action() {
            return this.mode === 'write'
                ? '{{ route('company-policies.store') }}'
                : '{{ route('company-documents.store') }}';
        }
     }">

    <div>
        <a href="{{ route('documents-admin.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Documents
        </a>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Publish something to staff</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Fill this in once. Where it is stored follows from what you put in it — you don't need to decide.
        </p>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">
            {{ $errors->first() }}
        </div>
    @endif

    <form :action="action" method="POST" enctype="multipart/form-data"
          class="rounded-2xl border border-slate-200/80 bg-white p-5 space-y-5 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        @csrf

        <div>
            <label class="{{ $lbl }}" for="d-title">Title</label>
            <input id="d-title" type="text" name="title" value="{{ old('title') }}" maxlength="255" required class="{{ $inp }}" placeholder="e.g. Leave Policy, Employment Contract">
        </div>

        <div>
            <label class="{{ $lbl }}" for="d-desc">Description <span class="font-normal normal-case text-slate-400">(optional)</span></label>
            <input id="d-desc" type="text" name="description" value="{{ old('description') }}" maxlength="1000" class="{{ $inp }}" placeholder="One line on what this is for">
        </div>

        {{-- The one question that decides everything else. --}}
        <div>
            <span class="{{ $lbl }}">What goes in it</span>
            <div class="grid gap-2 sm:grid-cols-2">
                @if($canWrite)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3.5 py-3 transition"
                           :class="mode === 'write' ? 'border-brand-400 bg-brand-50/40 dark:bg-brand-500/5' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" x-model="mode" value="write" class="mt-0.5 h-4 w-4 text-brand-600">
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Write it here</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Type the text in the app. Best for policies you maintain yourself.</span>
                        </span>
                    </label>
                @endif
                @if($canUpload)
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3.5 py-3 transition"
                           :class="mode === 'upload' ? 'border-brand-400 bg-brand-50/40 dark:bg-brand-500/5' : 'border-slate-200 dark:border-slate-700'">
                        <input type="radio" x-model="mode" value="upload" class="mt-0.5 h-4 w-4 text-brand-600">
                        <span>
                            <span class="block text-sm font-bold text-slate-800 dark:text-white">Upload a file</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">A Word or PDF you already have. Signature boxes can be placed on it.</span>
                        </span>
                    </label>
                @endif
            </div>
        </div>

        {{-- Written content --}}
        <div x-show="mode === 'write'" x-cloak>
            <label class="{{ $lbl }}" for="d-content">The text</label>
            <textarea id="d-content" name="content" rows="8" :disabled="mode !== 'write'" class="{{ $inp }}" placeholder="Write the policy here…">{{ old('content') }}</textarea>
        </div>

        {{-- Uploaded file --}}
        <div x-show="mode === 'upload'" x-cloak>
            <label class="{{ $lbl }}" for="d-file">The file</label>
            <input id="d-file" type="file" name="file" :disabled="mode !== 'upload'" :required="mode === 'upload'" class="{{ $inp }} file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-bold dark:file:bg-slate-700 dark:file:text-slate-200">
        </div>

        {{-- Each side files things its own way, so only the relevant one is sent. --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div x-show="mode === 'write'" x-cloak>
                <label class="{{ $lbl }}" for="d-cat">Category</label>
                <select id="d-cat" name="category" :disabled="mode !== 'write'" class="{{ $inp }}">
                    @foreach($policyCategories as $c)
                        <option value="{{ $c }}" @selected(old('category') === $c)>{{ ucwords(str_replace('_', ' & ', $c)) }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="mode === 'upload'" x-cloak>
                <label class="{{ $lbl }}" for="d-cat-id">Category</label>
                <select id="d-cat-id" name="category_id" :disabled="mode !== 'upload'" :required="mode === 'upload'" class="{{ $inp }}">
                    @foreach($documentCategories as $c)
                        <option value="{{ $c->id }}" @selected((string) old('category_id') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $lbl }}" for="d-version">Version</label>
                <input id="d-version" type="text" name="version" value="{{ old('version', '1.0') }}" maxlength="20" class="{{ $inp }}">
            </div>
        </div>

        {{-- Who can see it. Only the uploaded side stores this. --}}
        <div x-show="mode === 'upload'" x-cloak>
            <label class="{{ $lbl }}" for="d-access">Who it goes to</label>
            <select id="d-access" name="access_level" :disabled="mode !== 'upload'" :required="mode === 'upload'" class="{{ $inp }}">
                <option value="company_wide">Everyone</option>
                <option value="department">A department</option>
                <option value="specific_users">Specific people</option>
            </select>
            <p class="mt-1 text-[11px] text-slate-400">Pick the people on the next screen if it is not everyone.</p>
        </div>

        {{-- The point of the whole form. --}}
        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-900/30">
            <span class="{{ $lbl }}">What you need back</span>
            <label class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="requires_acknowledgment" value="1" @checked(old('requires_acknowledgment'))
                       class="h-4 w-4 rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-slate-900">
                They confirm they have read it
            </label>
            <label class="mt-2 flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="requires_signature" value="1" @checked(old('requires_signature'))
                       class="h-4 w-4 rounded border-slate-300 text-brand-600 dark:border-slate-600 dark:bg-slate-900">
                They sign it
            </label>
            <p class="mt-2 text-[11px] text-slate-400">Leave both unticked to simply share it.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div x-show="mode === 'write'" x-cloak>
                <label class="{{ $lbl }}" for="d-eff">Effective from</label>
                <input id="d-eff" type="date" name="effective_date" value="{{ old('effective_date') }}" :disabled="mode !== 'write'" class="{{ $inp }}">
            </div>
            <div x-show="mode === 'upload'" x-cloak>
                <label class="{{ $lbl }}" for="d-exp">Expires</label>
                <input id="d-exp" type="date" name="expires_at" value="{{ old('expires_at') }}" :disabled="mode !== 'upload'" class="{{ $inp }}">
            </div>
            <div x-show="mode === 'write'" x-cloak>
                <label class="{{ $lbl }}" for="d-deadline">Days to acknowledge</label>
                <input id="d-deadline" type="number" name="acknowledgment_deadline" value="{{ old('acknowledgment_deadline') }}" min="0" max="365" :disabled="mode !== 'write'" class="{{ $inp }}" placeholder="e.g. 14">
            </div>
        </div>

        <input type="hidden" name="status" value="active" :disabled="mode !== 'write'">

        <div class="flex items-center justify-end gap-2 pt-1">
            <a href="{{ route('documents-admin.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</a>
            <button type="submit" class="btn-brand"><i data-lucide="send" class="h-4 w-4"></i> Create</button>
        </div>
    </form>

</div>
@endsection
