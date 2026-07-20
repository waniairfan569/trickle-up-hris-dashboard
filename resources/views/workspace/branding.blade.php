@extends('layouts.hr-app')

@section('title', 'Workspace Branding')
@section('breadcrumb', 'Workspace Branding')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="palette" class="h-6 w-6 text-brand-500"></i> Workspace Branding
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">White-label your workspace — this is what your team sees in the sidebar and browser tab.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('workspace.branding.update') }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-6">
        @csrf @method('PUT')

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Workspace name</label>
            <input type="text" name="brand_name" value="{{ old('brand_name', $tenant->brand_name ?: $tenant->name) }}" maxlength="60" required
                   class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            <p class="text-[11px] text-slate-400 mt-1">Shown in the sidebar and browser tab.</p>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Accent colour</label>
            <div class="flex items-center gap-3">
                <input type="color" name="primary_color" value="{{ old('primary_color', $tenant->primary_color ?: '#fcd82f') }}"
                       class="h-10 w-16 rounded-lg border border-slate-300 dark:border-slate-600 cursor-pointer bg-white p-1">
                <span class="text-xs text-slate-400">Used for buttons and highlights. Pick a colour that reads well with dark text.</span>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Logo</label>
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-700 overflow-hidden">
                    <img src="{{ $tenant->logo_url ?: asset('images/logo.png') }}" alt="logo" class="h-10 w-10 object-contain">
                </div>
                <div class="flex-1">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                           class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                    <p class="text-[11px] text-slate-400 mt-1">PNG, JPG, SVG or WebP · up to 2&nbsp;MB. Square works best.</p>
                    @if($tenant->logo_url)
                        <label class="inline-flex items-center gap-1.5 mt-2 text-[11px] font-semibold text-rose-600 cursor-pointer">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-rose-600"> Remove current logo
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Save branding</button>
        </div>
    </form>
</div>
@endsection
