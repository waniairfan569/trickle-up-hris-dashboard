@extends('layouts.hr-app')

@section('title', 'Create a document')
@section('breadcrumb', 'Documents')

@section('content')
@php
    $tones = [
        'brand'   => 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400',
        'indigo'  => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400',
        'amber'   => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
    ];
@endphp

<div class="space-y-6">

    <div>
        <a href="{{ route('documents-admin.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600 dark:text-slate-400">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Documents
        </a>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">What are you making?</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
            Pick what you want to happen and this will take you to the right place. You don't
            need to know what the system calls it.
        </p>
    </div>

    @if(empty($choices))
        <p class="rounded-2xl border border-slate-200/80 bg-white px-5 py-10 text-center text-sm text-slate-400 dark:bg-slate-800 dark:border-slate-700">
            No document features are on this workspace's plan yet.
        </p>
    @else
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($choices as $c)
                <a href="{{ route($c['route'], $c['params'] ?? []) }}"
                   class="group flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:border-brand-400 hover:shadow-md dark:bg-slate-800 dark:border-slate-700 dark:hover:border-brand-500/50">
                    <span class="grid place-items-center h-10 w-10 rounded-xl {{ $tones[$c['tone']] ?? $tones['brand'] }}">
                        <i data-lucide="{{ $c['icon'] }}" class="h-5 w-5"></i>
                    </span>
                    <span class="mt-3 font-bold text-slate-800 dark:text-white">{{ $c['title'] }}</span>
                    <span class="mt-1.5 text-[13.5px] leading-relaxed text-slate-500 dark:text-slate-400">{{ $c['text'] }}</span>
                    <span class="mt-auto pt-3 text-[11.5px] text-slate-400 dark:text-slate-500">{{ $c['eg'] }}</span>
                    <span class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-brand-600 dark:text-brand-400">
                        Continue <i data-lucide="arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"></i>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

</div>
@endsection
