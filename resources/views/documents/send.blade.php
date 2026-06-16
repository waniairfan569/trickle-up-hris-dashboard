@extends('layouts.hr-app')

@section('title', 'Send for signature')
@section('breadcrumb', 'Document Templates')

@section('content')
<style>[x-cloak]{display:none!important}</style>

@php
    $employeeOpts = $employees->map(fn ($e) => [
        'id' => (string) $e->id,
        'label' => trim(($e->last_name ? $e->last_name . ', ' : '') . $e->first_name) . ($e->job_title ? ' — ' . $e->job_title : ''),
    ])->values();
@endphp
<script>window.__sendEmployees = @json($employeeOpts);</script>

<div class="max-w-2xl mx-auto space-y-6" x-data="{ employees: window.__sendEmployees || [], employeeId: '', open: false, q: '',
        label() { const e = this.employees.find(o => o.id === this.employeeId); return e ? e.label : 'Select an employee…'; } }">
    <div class="flex items-center gap-3">
        <a href="{{ route('document-templates.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Send for signature</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $documentTemplate->name }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('document-templates.send', $documentTemplate) }}"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-5">
        @csrf

        <!-- Signing order preview -->
        <div>
            <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Signing order</span>
            @forelse($documentTemplate->signers as $s)
                <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 py-1">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-700">{{ $loop->iteration }}</span>
                    {{ $s->display_name }}
                </div>
            @empty
                <p class="text-sm text-rose-600">No signers configured on this template — edit it and add signers first.</p>
            @endforelse
            <p class="text-[11px] text-slate-400 mt-1">Roles resolve to real people for the chosen employee (Employee → them, Line manager → their manager, HR admin → you).</p>
        </div>

        <!-- Employee picker -->
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Employee (document subject)</label>
            <input type="hidden" name="employee" :value="employeeId">
            <div @click.outside="open = false" class="relative">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
                    <span :class="employeeId ? 'text-slate-700 dark:text-white' : 'text-slate-400'" x-text="label()"></span>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top
                     class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                    <input x-model="q" type="text" placeholder="Search employees…" class="w-full rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <div class="max-h-60 overflow-y-auto mt-2 space-y-0.5">
                        <template x-for="e in employees.filter(o => o.label.toLowerCase().includes(q.toLowerCase()))" :key="e.id">
                            <button type="button" @click="employeeId = e.id; open = false"
                                    class="w-full text-left px-2 py-1.5 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                    :class="employeeId === e.id ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-500/10' : 'text-slate-700 dark:text-slate-200'">
                                <span x-text="e.label"></span>
                            </button>
                        </template>
                        <p x-show="!employees.length" class="text-xs text-slate-400 px-2 py-1">No eligible employees for this template's scope.</p>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" :disabled="!employeeId" :class="!employeeId && 'opacity-50 cursor-not-allowed'"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
            <i data-lucide="send" class="h-4 w-4"></i> Send for signature
        </button>
    </form>
</div>
@endsection
