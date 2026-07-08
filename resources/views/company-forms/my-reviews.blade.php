@extends('layouts.hr-app')

@section('title', 'Form Reviews')
@section('breadcrumb', 'Form Reviews')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="clipboard-check" class="h-6 w-6 text-brand-500"></i> Form Reviews
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Forms you can review — open the responses to approve, reject, or leave a suggestion.</p>
    </div>

    <div class="space-y-3">
        @forelse($forms as $form)
            <a href="{{ route('company-forms.responses', $form) }}" class="flex items-center justify-between gap-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 hover:border-brand-300 transition dark:bg-slate-800 dark:border-slate-700">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $form->title }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $form->submissions_count }} {{ \Illuminate\Support\Str::plural('response', $form->submissions_count) }}</p>
                </div>
                <span class="inline-flex items-center gap-1 text-sm font-bold text-brand-600 shrink-0">Review <i data-lucide="arrow-right" class="h-4 w-4"></i></span>
            </a>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="clipboard-check" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">Nothing to review</p>
                <p class="text-xs text-slate-400 mt-1">Forms you're given access to review will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
