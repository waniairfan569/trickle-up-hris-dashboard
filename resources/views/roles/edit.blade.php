@extends('layouts.hr-app')

@section('title', 'Edit ' . $role->name)
@section('breadcrumb', 'Roles · Edit')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto space-y-6">
    <div>
        <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white mb-2"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Roles</a>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">Edit {{ $role->name }} @if($role->is_system)<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">System</span>@endif</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Choose what this role can access. Changes apply to everyone with this role.</p>
    </div>

    <form method="POST" action="{{ route('roles.update', $role) }}">
        @csrf @method('PUT')
        @include('roles._form')
    </form>
</div>
@endsection
