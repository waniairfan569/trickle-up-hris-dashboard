@extends('layouts.hr-app')

@section('title', 'Documents')
@section('breadcrumb', 'Documents')

@section('content')
<style>
    .policy-content h1{font-size:1.4rem;font-weight:800;margin:1rem 0 .5rem}
    .policy-content h2{font-size:1.2rem;font-weight:700;margin:1rem 0 .5rem}
    .policy-content h3{font-size:1.05rem;font-weight:700;margin:.75rem 0 .5rem}
    .policy-content p{margin:.5rem 0;line-height:1.7}
    .policy-content ul{list-style:disc;padding-left:1.5rem;margin:.5rem 0}
    .policy-content ol{list-style:decimal;padding-left:1.5rem;margin:.5rem 0}
    .policy-content a{color:#2563eb;text-decoration:underline}
</style>

<div class="space-y-6" x-data="{ active: '{{ $tab }}' }"
     x-init="$watch('active', v => { try { const u = new URL(location); u.searchParams.set('tab', v); history.replaceState(null, '', u); } catch (e) {} })">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Documents</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Sign, review, and manage all documents, policies, and forms assigned to you.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif

    {{-- Hub tab bar --}}
    @php
        $hubTabs = [
            ['key' => 'all',      'label' => 'All',      'count' => 0],
            ['key' => 'sign',     'label' => 'To Sign',  'count' => $counts['sign']],
            ['key' => 'policies', 'label' => 'Policies', 'count' => $counts['policies']],
        ];
        if ($formsEnabled) {
            $hubTabs[] = ['key' => 'forms', 'label' => 'Forms', 'count' => $counts['forms']];
        }
    @endphp
    <div class="flex items-center gap-1 border-b border-slate-200 dark:border-slate-700 overflow-x-auto">
        @foreach($hubTabs as $t)
            <button type="button" @click="active = '{{ $t['key'] }}'"
                    :class="active === '{{ $t['key'] }}' ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600'"
                    class="inline-flex items-center gap-1.5 border-b-2 px-4 py-2.5 text-sm font-bold transition whitespace-nowrap">
                {{ $t['label'] }}
                @if($t['count'] > 0)
                    <span class="inline-flex items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold h-4 min-w-[16px] px-1">{{ $t['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div x-show="active === 'all'" x-cloak>@include('employee.documents-hub._all')</div>
    <div x-show="active === 'sign'" x-cloak>@include('employee.documents-hub._to-sign')</div>
    <div x-show="active === 'policies'" x-cloak>@include('employee.documents-hub._policies')</div>
    @if($formsEnabled)
        <div x-show="active === 'forms'" x-cloak>@include('employee.documents-hub._forms')</div>
    @endif
</div>
@endsection
