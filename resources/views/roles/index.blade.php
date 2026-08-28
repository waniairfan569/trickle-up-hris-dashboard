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
            <a href="{{ route('roles.create') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm hover:bg-brand-700 transition duration-150">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span>Add Role</span>
            </a>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20 flex items-center gap-2"><i data-lucide="alert-triangle" class="h-5 w-5"></i>{{ session('error') }}</div>@endif

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($roles as $role)
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">{{ $role->name }}@if($role->is_system)<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-400 dark:bg-slate-700">System</span>@endif</h3>
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
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs font-semibold text-slate-400">{{ $role->slug === 'super_admin' ? 'All' : $role->permissions_count }} permission{{ ($role->slug === 'super_admin' || $role->permissions_count != 1) ? 's' : '' }}</span>
                <span class="text-slate-200 dark:text-slate-600">·</span>
                <a href="{{ route('roles.show', $role) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"><i data-lucide="eye" class="h-4 w-4"></i> Preview</a>
                <a href="{{ route('roles.edit', $role) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"><i data-lucide="pencil" class="h-4 w-4"></i> Edit Permissions</a>
                @if(!$role->is_system && $role->users_count === 0)
                    <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Delete the {{ $role->name }} role?')" class="ml-auto">@csrf @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-1 text-sm font-semibold text-rose-500 hover:text-rose-700"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </form>
                @endif
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
