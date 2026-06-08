@props(['role'])

@php
    // Resolve slug and name whether role is passed as a string or a full Role object
    $slug = is_string($role) ? $role : ($role->slug ?? '');
    $name = is_string($role) ? ucwords(str_replace('_', ' ', $role)) : ($role->name ?? '');

    $classes = match($slug) {
        'super_admin' => 'bg-rose-50 text-rose-700 border-rose-200 ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
        'hr_admin'    => 'bg-amber-50 text-amber-700 border-amber-200 ring-amber-600/10 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
        'manager'     => 'bg-indigo-50 text-indigo-700 border-indigo-200 ring-indigo-600/10 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20',
        'employee'    => 'bg-emerald-50 text-emerald-700 border-emerald-200 ring-emerald-600/10 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
        default       => 'bg-slate-50 text-slate-700 border-slate-200 ring-slate-600/10 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20',
    };
@endphp

<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold border ring-1 ring-inset {{ $classes }}">
    {{ $name }}
</span>
