@extends('layouts.hr-app')

@section('title', 'Feature access · ' . $employee->first_name)
@section('breadcrumb', 'Feature access')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
        allKeys: {{ Illuminate\Support\Js::from(collect($grouped)->flatMap(fn($f)=>array_keys($f))->values()) }},
        check(v){ this.$root.querySelectorAll('input[data-feat]:not(:disabled)').forEach(c=>c.checked=v); }
    }">
    <div class="flex items-center gap-3">
        <a href="{{ route('employees.profile', $employee->id) }}" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div class="flex items-center gap-3">
            <span class="h-11 w-11 shrink-0 rounded-full overflow-hidden bg-gradient-to-br from-brand-400 to-indigo-500 grid place-items-center text-white text-sm font-bold">
                @if($employee->avatar_url)<img src="{{ $employee->avatar_url }}" class="h-full w-full object-cover">@else{{ strtoupper(substr($employee->first_name,0,1).substr($employee->last_name,0,1)) }}@endif
            </span>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Feature access</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Grant {{ trim($employee->first_name.' '.$employee->last_name) }} access to specific features, on top of their role.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif

    <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800/60 dark:border-slate-700 dark:text-slate-400 flex gap-2">
        <i data-lucide="shield" class="h-4 w-4 shrink-0 mt-0.5 text-slate-400"></i>
        <span>Profile <strong>Information</strong> (personal details) and <strong>Compensation / pay</strong> are confidential and can never be granted here. Granting a feature also requires it to be part of your plan.</span>
    </div>

    <form method="POST" action="{{ route('employees.access.update', $employee->id) }}" class="space-y-5">
        @csrf @method('PUT')

        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-400">Tick the features to grant. Items marked <span class="text-indigo-500 font-bold">via role</span> already come from a role.</p>
            <div class="flex items-center gap-2 text-xs font-bold">
                <button type="button" @click="check(true)" class="rounded-lg border border-slate-200 px-2.5 py-1 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Select all</button>
                <button type="button" @click="check(false)" class="rounded-lg border border-slate-200 px-2.5 py-1 text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Clear</button>
            </div>
        </div>

        @forelse($grouped as $group => $features)
            <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
                <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-700/60 bg-slate-50/60 dark:bg-slate-900/30">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $group }}</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 p-4">
                    @foreach($features as $key => $label)
                        @php $viaRole = in_array($key, $viaRoles, true); $granted = in_array($key, $direct, true); @endphp
                        <label class="flex items-center gap-3 rounded-lg px-2.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-700/40 cursor-pointer {{ $viaRole ? 'opacity-70' : '' }}">
                            <input type="checkbox" name="features[]" value="{{ $key }}" data-feat
                                   @checked($granted || $viaRole) @disabled($viaRole)
                                   class="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-slate-700 dark:text-slate-200 flex-1">{{ $label }}</span>
                            @if($viaRole)<span class="text-[10px] font-bold rounded-full bg-indigo-50 text-indigo-600 px-2 py-0.5 dark:bg-indigo-500/10 dark:text-indigo-400">via role</span>@endif
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-600">
                No grantable features are available on your current plan.
            </div>
        @endforelse

        @if(!empty($grouped))
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('employees.profile', $employee->id) }}" class="btn-outline">Cancel</a>
                <button type="submit" class="btn-brand"><i data-lucide="check" class="h-4 w-4"></i> Save access</button>
            </div>
        @endif
    </form>
</div>
<script>window.lucide && lucide.createIcons();</script>
@endsection
