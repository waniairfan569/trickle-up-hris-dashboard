@extends('layouts.hr-app')

@section('title', $sheet->name)
@section('breadcrumb', 'Sheets · ' . $sheet->name)

@section('content')
<div class="max-w-full mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('sheets.index') }}" class="inline-grid place-items-center h-9 w-9 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700 shrink-0">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
            </a>
            <div class="min-w-0">
                <h1 class="text-lg font-extrabold tracking-tight text-slate-900 dark:text-white truncate">{{ $sheet->name }}</h1>
                <p class="text-[11px] text-slate-400">{{ $sheet->provider_label }}@if($sheet->category) · {{ $sheet->category }}@endif</p>
            </div>
        </div>
        <a href="{{ route('sheets.open', $sheet) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-400 shrink-0">
            <i data-lucide="external-link" class="h-4 w-4"></i> Open in {{ $sheet->is_google ? 'Google' : 'source' }}
        </a>
    </div>

    @if($sheet->description)
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $sheet->description }}</p>
    @endif

    {{-- Embedded sheet --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white overflow-hidden shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <iframe src="{{ $sheet->embed_url }}"
                class="w-full"
                style="height: calc(100vh - 240px); min-height: 480px; border: 0;"
                loading="lazy"
                referrerpolicy="no-referrer"
                title="{{ $sheet->name }}"></iframe>
    </div>

    <p class="text-[11px] text-slate-400 flex items-center gap-1.5">
        <i data-lucide="info" class="h-3.5 w-3.5"></i>
        If the sheet appears blank or asks for access, it isn’t shared for embedding — open it in {{ $sheet->is_google ? 'Google' : 'the source' }}, or set its sharing to “Anyone with the link · Viewer”.
    </p>
</div>
@endsection
