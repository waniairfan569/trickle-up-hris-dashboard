@extends('layouts.hr-app')

@section('title', $role->name . ' — Permissions')
@section('breadcrumb', 'Roles · Preview')

@php
    $moduleLabel = fn ($m) => \Illuminate\Support\Str::of($m)->replace('_', ' ')->title();
    $isSuper = $role->slug === \App\Models\Role::SUPER_ADMIN;
    $grantedCount = count($granted);
    $total = $modules->flatten()->count();
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white mb-2"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Roles</a>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">{{ $role->name }} @if($role->is_system)<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">System</span>@endif</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $isSuper ? 'Full access — every permission.' : $grantedCount . ' of ' . $total . ' permissions granted.' }}</p>
        </div>
        <a href="{{ route('roles.edit', $role) }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="pencil" class="h-4 w-4"></i> Edit permissions</a>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
        @foreach($modules as $module => $perms)
            <div>
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5"><i data-lucide="folder" class="h-3.5 w-3.5"></i> {{ $moduleLabel($module) }}</h3>
                <div class="space-y-1.5">
                    @foreach($perms as $perm)
                        @php $has = $isSuper || in_array($perm->id, $granted); @endphp
                        <div class="flex items-start gap-2.5 text-sm {{ $has ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500' }}">
                            <i data-lucide="{{ $has ? 'check-circle-2' : 'minus-circle' }}" class="h-4 w-4 mt-0.5 shrink-0 {{ $has ? 'text-emerald-500' : 'text-slate-300 dark:text-slate-600' }}"></i>
                            <span>{{ $perm->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
