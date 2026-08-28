@extends('layouts.hr-app')

@section('title', 'Add Role')
@section('breadcrumb', 'Roles · Add')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto space-y-6">
    <div>
        <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white mb-2"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Roles</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Add Role</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create a custom role and choose exactly what it can do.</p>
    </div>

    <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        @include('roles._form')
    </form>
</div>
@endsection
