@extends('layouts.hr-app')

@section('title', 'Roles & Permissions')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Roles & Permissions</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage system roles and their assigned permissions.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm hover:bg-brand-700 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span>Add Role</span>
            </button>
        </div>
    </div>

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($roles as $role)
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $role->name }}</h3>
                <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-600/20 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20">
                    {{ $role->users_count }} Users
                </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                @if($role->slug === 'super_admin')
                    Full access to all system features and settings.
                @elseif($role->slug === 'hr_admin')
                    Access to manage employees, time off, and organization settings.
                @elseif($role->slug === 'manager')
                    Access to manage team attendance, schedules, and approvals.
                @else
                    Standard employee access to personal profile and requests.
                @endif
            </p>
            <div class="flex items-center gap-3">
                <button class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300">Edit Permissions</button>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
