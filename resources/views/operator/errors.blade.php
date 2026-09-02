@extends('layouts.operator')

@section('title', 'Errors')
@section('breadcrumb', 'Errors')

@section('content')
<div class="max-w-5xl">
    <div class="flex items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="bug" class="h-6 w-6 text-indigo-500"></i> Application errors
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Real errors clients hit (5xx / unexpected) — 404s, form validation and permission checks are excluded.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400">{{ session('success') }}</div>
    @endif

    {{-- Filter tabs --}}
    <div class="flex items-center gap-2 mb-4">
        @foreach(['open'=>'Open', 'resolved'=>'Resolved', 'all'=>'All'] as $key => $label)
            <a href="{{ route('operator.errors', ['filter'=>$key]) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-bold transition {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' }}">
                {{ $label }}@if($key === 'open') <span class="ml-1 text-xs {{ $filter === $key ? 'text-indigo-200' : 'text-slate-400' }}">{{ $openCount }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        @forelse($appErrors as $err)
            <div x-data="{ open: false }" class="border-b border-slate-100 last:border-0 dark:border-slate-700/50">
                <button type="button" @click="open = !open" class="w-full flex items-start gap-3 px-5 py-4 text-left hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition">
                    <span class="shrink-0 mt-0.5 grid place-items-center h-7 w-7 rounded-lg {{ $err->resolved_at ? 'bg-slate-100 text-slate-400 dark:bg-slate-700' : 'bg-rose-50 text-rose-500 dark:bg-rose-500/10' }}">
                        <i data-lucide="{{ $err->resolved_at ? 'check' : 'alert-triangle' }}" class="h-4 w-4"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-bold text-slate-800 dark:text-white">{{ $err->exception }}</span>
                            @if($err->status_code)<span class="text-[10px] font-mono font-bold rounded bg-rose-100 text-rose-700 px-1.5 py-0.5 dark:bg-rose-500/15 dark:text-rose-300">{{ $err->status_code }}</span>@endif
                            @if($err->occurrences > 1)<span class="text-[10px] font-bold rounded-full bg-slate-100 text-slate-500 px-2 py-0.5 dark:bg-slate-700 dark:text-slate-300">×{{ $err->occurrences }}</span>@endif
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">{{ $err->message }}</p>
                        <p class="text-[11px] text-slate-400 mt-1">
                            {{ $err->method }} {{ \Illuminate\Support\Str::limit($err->url, 60) }}
                            · {{ $err->updated_at->diffForHumans() }}
                            @if($err->tenant) · {{ $err->tenant->name }}@endif
                            @if($err->user) · {{ $err->user->email }}@endif
                        </p>
                    </div>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 shrink-0 transition" :class="open ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="open" x-cloak class="px-5 pb-4 space-y-3">
                    <div class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $err->file }}:{{ $err->line }}</div>
                    @if($err->trace)
                        <pre class="overflow-x-auto rounded-lg bg-slate-900 text-slate-200 text-[11px] leading-relaxed p-3 max-h-64">{{ $err->trace }}</pre>
                    @endif
                    <div class="flex items-center gap-2">
                        @if($err->resolved_at)
                            <form method="POST" action="{{ route('operator.errors.reopen', $err) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">Reopen</button></form>
                        @else
                            <form method="POST" action="{{ route('operator.errors.resolve', $err) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">Mark resolved</button></form>
                        @endif
                        <form method="POST" action="{{ route('operator.errors.destroy', $err) }}" onsubmit="return confirm('Delete this error?');">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30">Delete</button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center">
                <i data-lucide="party-popper" class="h-8 w-8 text-emerald-500 mx-auto"></i>
                <p class="mt-2 text-sm font-semibold text-slate-600 dark:text-slate-300">No {{ $filter === 'open' ? 'open ' : '' }}errors. Nice and quiet.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $appErrors->links() }}</div>
</div>
@endsection
