@extends('layouts.hr-app')

@section('title', 'Security')
@section('breadcrumb', 'Company > Security')

@section('content')
<div class="space-y-6">

    <div>
        <a href="{{ route('company.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition mb-2"><i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Company</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Security</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Access control and accountability — who can do what, who's signed in, and what changed.
        </p>
    </div>

    @include('partials.hub-tiles', ['tiles' => $tiles])

</div>
@endsection
