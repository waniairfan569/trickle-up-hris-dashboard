@extends('layouts.hr-app')

@php $editing = $letterhead->exists; @endphp
@section('title', $editing ? 'Edit letterhead' : 'New letterhead')
@section('breadcrumb', $editing ? 'Edit letterhead' : 'New letterhead')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="file-signature" class="h-6 w-6 text-brand-500"></i> {{ $editing ? 'Edit letterhead' : 'New letterhead' }}
        </h1>
        @if($editing)
            <a href="{{ route('letterheads.preview', $letterhead) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-3.5 w-3.5"></i> Preview PDF</a>
        @endif
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $editing ? route('letterheads.update', $letterhead) : route('letterheads.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-6">
        @csrf
        @if($editing) @method('PUT') @endif

        {{-- Identity --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Letterhead name <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $letterhead->name) }}" maxlength="100" required placeholder="e.g. Official" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <p class="text-[11px] text-slate-400 mt-1">Internal label so you can pick it later.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Legal entity</label>
                <select name="company_entity_id" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="">— none —</option>
                    @foreach($entities as $ent)
                        <option value="{{ $ent->id }}" @selected((int) old('company_entity_id', $letterhead->company_entity_id) === $ent->id)>{{ $ent->legal_name ?: $ent->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Company name (header) <span class="text-rose-500">*</span></label>
                <input type="text" name="company_name" value="{{ old('company_name', $letterhead->company_name) }}" maxlength="120" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Subtitle</label>
                <input type="text" name="company_suffix" value="{{ old('company_suffix', $letterhead->company_suffix) }}" maxlength="120" placeholder="e.g. Private Limited" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
        </div>

        {{-- Logo --}}
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Logo</label>
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-700 overflow-hidden">
                    <img src="{{ $letterhead->logo_path ? \Illuminate\Support\Facades\Storage::url($letterhead->logo_path) : asset('images/logo.png') }}" alt="logo" class="h-10 w-10 object-contain">
                </div>
                <div class="flex-1">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    <p class="text-[11px] text-slate-400 mt-1">Up to 2&nbsp;MB. Leave blank to use the workspace logo.</p>
                    @if($editing && $letterhead->logo_path)
                        <label class="inline-flex items-center gap-1.5 mt-2 text-[11px] font-semibold text-rose-600 cursor-pointer"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-rose-600"> Remove logo</label>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ready-made band images (override the text header/footer) --}}
        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Header band image <span class="normal-case font-medium text-slate-400">(optional)</span></label>
                @if($editing && $letterhead->header_image_path)<img src="{{ \Illuminate\Support\Facades\Storage::url($letterhead->header_image_path) }}" class="h-8 mb-2">@endif
                <input type="file" name="header_image" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                @if($editing && $letterhead->header_image_path)<label class="inline-flex items-center gap-1.5 mt-1 text-[11px] font-semibold text-rose-600 cursor-pointer"><input type="checkbox" name="remove_header_image" value="1" class="rounded border-slate-300 text-rose-600"> Remove</label>@endif
                <p class="text-[11px] text-slate-400 mt-1">A brand wordmark. Replaces the company-name header.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Footer band image <span class="normal-case font-medium text-slate-400">(optional)</span></label>
                @if($editing && $letterhead->footer_image_path)<img src="{{ \Illuminate\Support\Facades\Storage::url($letterhead->footer_image_path) }}" class="w-full rounded mb-2" style="max-height:36px;object-fit:cover;">@endif
                <input type="file" name="footer_image" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                @if($editing && $letterhead->footer_image_path)<label class="inline-flex items-center gap-1.5 mt-1 text-[11px] font-semibold text-rose-600 cursor-pointer"><input type="checkbox" name="remove_footer_image" value="1" class="rounded border-slate-300 text-rose-600"> Remove</label>@endif
                <p class="text-[11px] text-slate-400 mt-1">A full-width contact strip. Replaces the address/contact footer.</p>
            </div>
        </div>

        {{-- Watermark --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Watermark image <span class="normal-case font-medium text-slate-400">(optional)</span></label>
                @if($editing && $letterhead->watermark_image_path)<img src="{{ \Illuminate\Support\Facades\Storage::url($letterhead->watermark_image_path) }}" class="h-10 mb-2 opacity-50">@endif
                <input type="file" name="watermark_image" accept="image/png,image/svg+xml,image/webp" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                @if($editing && $letterhead->watermark_image_path)<label class="inline-flex items-center gap-1.5 mt-1 text-[11px] font-semibold text-rose-600 cursor-pointer"><input type="checkbox" name="remove_watermark_image" value="1" class="rounded border-slate-300 text-rose-600"> Remove</label>@endif
                <p class="text-[11px] text-slate-400 mt-1">A faint brand mark behind the page body.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Watermark opacity</label>
                <input type="number" name="watermark_opacity" min="0" max="1" step="0.01" value="{{ old('watermark_opacity', $letterhead->watermark_opacity ?: 0.06) }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <p class="text-[11px] text-slate-400 mt-1">0 = invisible, 1 = solid. Around 0.06–0.10 is subtle.</p>
            </div>
        </div>

        {{-- Contact / footer --}}
        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Address (footer)</label>
            <textarea name="address" rows="2" maxlength="400" placeholder="Street, city, postcode, country" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('address', $letterhead->address) }}</textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email</label>
                <input type="text" name="email" value="{{ old('email', $letterhead->email) }}" maxlength="150" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Website</label>
                <input type="text" name="website" value="{{ old('website', $letterhead->website) }}" maxlength="150" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $letterhead->phone) }}" maxlength="60" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
        </div>

        {{-- Colours --}}
        <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Colours</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach([['header_bg','Header bg','#FFFDF5'],['header_accent','Header line','#fcd82f'],['footer_bg','Footer bg','#1F5FD6'],['footer_text','Footer text','#ffffff']] as [$key,$lbl,$def])
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">{{ $lbl }}</label>
                        <input type="color" name="{{ $key }}" value="{{ old($key, $letterhead->$key ?: $def) }}" class="h-10 w-full rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-white p-1">
                    </div>
                @endforeach
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $letterhead->is_default)) class="rounded border-slate-300 text-brand-600"> Make this the default letterhead
        </label>

        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('letterheads.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">Cancel</a>
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">{{ $editing ? 'Save changes' : 'Create letterhead' }}</button>
        </div>
    </form>
</div>
@endsection
