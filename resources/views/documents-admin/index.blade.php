@extends('layouts.hr-app')

@section('title', 'Documents')
@section('breadcrumb', 'Documents')

@section('content')
<div class="space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Documents</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Everything you send to people and get back — signed, acknowledged or answered.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('documents-admin.all') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">
                <i data-lucide="list" class="h-4 w-4"></i> See everything
            </a>
            <a href="{{ route('documents-admin.create') }}" class="btn-brand">
                <i data-lucide="plus" class="h-4 w-4"></i> Create a document
            </a>
        </div>
    </div>

    @foreach($groups as $group)
        @continue(empty($group['tiles']))
        <div class="space-y-3">
            <div class="flex items-baseline gap-2.5">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-300">{{ $group['label'] }}</h2>
                <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
            </div>
            @include('partials.hub-tiles', ['tiles' => $group['tiles']])
        </div>
    @endforeach

</div>
@endsection
