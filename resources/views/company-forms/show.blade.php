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

        @if($form->is_monthly)
            <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-brand-200 bg-brand-50/50 p-3 dark:border-brand-500/20 dark:bg-brand-500/5">
                <i data-lucide="calendar-clock" class="h-5 w-5 text-brand-600 shrink-0"></i>
                <p class="text-xs text-slate-600 dark:text-slate-300 flex-1 min-w-[12rem]">
                    <span class="font-bold">Monthly form.</span> Everyone assigned is re-notified to submit each month (auto-opens on the 1st). Need it opened now for <span class="font-bold">{{ \App\Models\CompanyForm::periodLabel(now()->format('Y-m')) }}</span>?
                </p>
                <form action="{{ route('company-forms.open-month', $form) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-white border border-brand-300 px-4 py-2 text-xs font-bold text-brand-700 hover:bg-brand-50 dark:bg-slate-800 dark:border-slate-600 dark:text-brand-300"><i data-lucide="send" class="h-3.5 w-3.5 inline -mt-0.5"></i> Open for {{ now()->format('F') }}</button>
                </form>
            </div>
        @endif
    </div>

    <!-- Current assignments -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-800 dark:text-white">Assignments ({{ $form->assignments->count() }})</h2></div>
        @forelse($form->assignments as $a)
            <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="{{ $a->assigned_to_type === 'all' ? 'users' : ($a->assigned_to_type === 'department' ? 'building-2' : 'user') }}" class="h-4 w-4"></i></span>
                <span class="text-sm font-semibold text-slate-800 dark:text-white">{{ $a->label }}</span>
                <span class="text-xs text-slate-400 ml-auto">{{ optional($a->assigned_at)->format('d M Y') }}</span>
                <form action="{{ route('company-forms.unassign', [$form, $a]) }}" method="POST" onsubmit="return confirm('Unassign “{{ $a->label }}” from this form?\n\nPending (not-yet-submitted) responses for these people will be removed. Already-submitted responses are kept.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" title="Unassign" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"><i data-lucide="x" class="h-4 w-4"></i></button>
                </form>
            </div>
        @empty
            <p class="px-6 py-8 text-center text-sm text-slate-400">Not assigned to anyone yet.</p>
        @endforelse
    </div>

    @if(auth()->user()->hasRole('super_admin'))
    <!-- Reviewers -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Reviewers</h2>
            <p class="text-xs text-slate-400 mt-0.5">These people can view responses and approve / reject / leave a suggestion — even if they aren't admins.</p>
        </div>
        <form action="{{ route('company-forms.reviewers.add', $form) }}" method="POST" class="flex flex-wrap items-end gap-3 p-6 border-b border-slate-100 dark:border-slate-700/60">
            @csrf
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Grant review access to</label>
                <select name="user_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach($users as $u)<option value="{{ $u->id }}">{{ trim(($u->last_name ? $u->last_name.', ' : '').$u->first_name) }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="user-plus" class="h-4 w-4 inline -mt-0.5"></i> Add reviewer</button>
        </form>
        @forelse($form->reviewers as $r)
            <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10"><i data-lucide="user-check" class="h-4 w-4"></i></span>
                <span class="text-sm font-semibold text-slate-800 dark:text-white">{{ trim($r->first_name.' '.$r->last_name) }}</span>
                <form action="{{ route('company-forms.reviewers.remove', [$form, $r]) }}" method="POST" class="ml-auto" onsubmit="return confirm('Remove review access for this person?');">
                    @csrf @method('DELETE')
                    <button type="submit" title="Remove reviewer" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"><i data-lucide="x" class="h-4 w-4"></i></button>
                </form>
            </div>
        @empty
            <p class="px-6 py-6 text-center text-sm text-slate-400">No extra reviewers yet — only admins can review this form's responses.</p>
        @endforelse
    </div>
    @endif
</div>
@endsection
