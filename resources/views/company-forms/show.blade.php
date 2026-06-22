@extends('layouts.hr-app')

@section('title', 'Assign · ' . $form->title)
@section('breadcrumb', 'Company Forms')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ atype: 'all' }">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('company-forms.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All forms</a>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white mt-1">{{ $form->title }}</h1>
        </div>
        <a href="{{ route('company-forms.responses', $form) }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">View responses</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($form->status === 'draft')
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700 dark:bg-amber-500/10 dark:border-amber-500/20">This form is a <b>draft</b> — assigned employees won't see it until you set the status to <b>Active</b> in the builder.</div>
    @endif

    <!-- Assign -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-4">Assign this form</h2>
        <form action="{{ route('company-forms.assign', $form) }}" method="POST" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Assign to</label>
                <select name="assigned_to_type" x-model="atype" class="rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="all">Whole company</option>
                    <option value="department">A department</option>
                    <option value="user">A specific employee</option>
                </select>
            </div>
            <div x-show="atype === 'department'" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Department</label>
                <select name="assigned_to_id" class="rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div x-show="atype === 'user'" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Employee</label>
                <select name="assigned_to_id" class="rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach($users as $u)<option value="{{ $u->id }}">{{ trim(($u->last_name ? $u->last_name.', ' : '').$u->first_name) }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="user-plus" class="h-4 w-4 inline -mt-0.5"></i> Assign</button>
        </form>
    </div>

    <!-- Current assignments -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Assignments ({{ $form->assignments->count() }})</h2></div>
        @forelse($form->assignments as $a)
            <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="{{ $a->assigned_to_type === 'all' ? 'users' : ($a->assigned_to_type === 'department' ? 'building-2' : 'user') }}" class="h-4 w-4"></i></span>
                <span class="text-sm font-semibold text-slate-800 dark:text-white">{{ $a->label }}</span>
                <span class="text-xs text-slate-400 ml-auto">{{ optional($a->assigned_at)->format('d M Y') }}</span>
            </div>
        @empty
            <p class="px-6 py-8 text-center text-sm text-slate-400">Not assigned to anyone yet.</p>
        @endforelse
    </div>
</div>
@endsection
