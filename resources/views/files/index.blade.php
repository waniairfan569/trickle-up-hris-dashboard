@extends('layouts.hr-app')

@section('title', 'Company Files')
@section('breadcrumb', 'Files')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Company Files</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Shared documents and resources for everyone.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @if($isAdmin)
        <!-- Upload (admins) -->
        <div x-data="{ name: '' }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-4">Upload a file</h2>
            <form action="{{ route('files.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title</label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g. Employee Handbook 2026"
                           class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">File <span class="text-slate-400 normal-case font-medium">(max 20 MB)</span></label>
                    <input type="file" name="file" required
                           class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                    <input type="text" name="description" value="{{ old('description') }}"
                           class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition">
                        <i data-lucide="upload" class="h-4 w-4"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- File list -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        @forelse($files as $f)
            <div class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                    <i data-lucide="file-text" class="h-5 w-5"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $f->title }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                        {{ $f->original_name }} · {{ $f->readable_size }}
                        @if($f->uploader) · {{ $f->uploader->full_name }} @endif
                        · {{ $f->created_at->format('d M Y') }}
                    </p>
                    @if($f->description)<p class="text-xs text-slate-400 mt-0.5 truncate">{{ $f->description }}</p>@endif
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('files.download', $f) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                        <i data-lucide="download" class="h-3.5 w-3.5"></i> Download
                    </a>
                    @if($isAdmin)
                        <form action="{{ route('files.destroy', $f) }}" method="POST" onsubmit="return confirm('Delete {{ $f->title }}? This cannot be undone.');">
                            @csrf @method('DELETE')
                            <button type="submit" title="Delete" class="inline-flex items-center justify-center rounded-xl bg-rose-50 h-8 w-8 text-rose-600 hover:bg-rose-100 dark:bg-rose-500/10">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="folder-open" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No files yet</p>
                <p class="text-xs text-slate-400 mt-1">{{ $isAdmin ? 'Upload a file to get started.' : 'Company documents will appear here.' }}</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
