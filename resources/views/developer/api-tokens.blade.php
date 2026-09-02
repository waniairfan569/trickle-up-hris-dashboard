@extends('layouts.hr-app')

@section('title', 'API access')
@section('breadcrumb', 'API access')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="code-2" class="h-6 w-6 text-brand-500"></i> API access
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create personal API tokens to integrate {{ config('legal.company', 'Trickle Hub') }} with your own tools.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}
        </div>
    @endif

    {{-- Freshly-created token (shown once) --}}
    @if(session('new_token'))
        <div class="rounded-2xl border border-brand-300 bg-brand-50/60 p-5 dark:border-brand-500/30 dark:bg-brand-500/5" x-data="{ copied: false }">
            <p class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="key-round" class="h-4 w-4 text-brand-600"></i> Your new token “{{ session('new_token_name') }}”</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Copy it now — for your security it won’t be shown again.</p>
            <div class="mt-3 flex items-center gap-2">
                <code x-ref="tok" class="flex-1 break-all rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200">{{ session('new_token') }}</code>
                <button type="button" @click="navigator.clipboard.writeText($refs.tok.innerText); copied = true; setTimeout(() => copied = false, 1500)"
                        class="shrink-0 rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800 dark:bg-slate-700">
                    <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied ✓</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Create --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-base font-bold text-slate-800 dark:text-white">Create a token</h2>
        <form method="POST" action="{{ route('developer.api-tokens.store') }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Token name</label>
                <input type="text" name="name" required maxlength="80" placeholder="e.g. Payroll export script"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                @error('name')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-brand btn-sm">Generate token</button>
        </form>
        <p class="mt-2 text-[11px] text-slate-400">A token acts with your own permissions. Keep it secret — anyone with it can call the API as you.</p>
    </div>

    {{-- Existing tokens --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Your tokens</h2></div>
        @forelse($tokens as $token)
            <div class="flex items-center justify-between px-6 py-3.5 border-b border-slate-50 last:border-0 dark:border-slate-700/40">
                <div>
                    <p class="text-sm font-bold text-slate-800 dark:text-white">{{ $token->name }}</p>
                    <p class="text-xs text-slate-400">Created {{ $token->created_at->diffForHumans() }} · {{ $token->last_used_at ? 'last used ' . $token->last_used_at->diffForHumans() : 'never used' }}</p>
                </div>
                <form method="POST" action="{{ route('developer.api-tokens.destroy', $token->id) }}" onsubmit="return confirm('Revoke “{{ $token->name }}”? Any integration using it will stop working.');">
                    @csrf @method('DELETE')
                    <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Revoke</button>
                </form>
            </div>
        @empty
            <p class="px-6 py-8 text-center text-sm text-slate-400">No tokens yet. Create one above to start using the API.</p>
        @endforelse
    </div>

    {{-- Docs --}}
    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 shadow-sm p-6 dark:bg-slate-900/40 dark:border-slate-700">
        <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="book-open" class="h-4 w-4 text-brand-500"></i> Using the API</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Send your token as a Bearer header. The base URL is:</p>
        <code class="mt-2 block rounded-lg bg-slate-900 px-3 py-2 text-xs font-mono text-slate-100">{{ url('/api/v1') }}</code>
        <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">Example — fetch the current account:</p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 px-3 py-3 text-xs font-mono text-slate-100"><code>curl {{ url('/api/v1/auth/me') }} \
  -H "Authorization: Bearer &lt;your-token&gt;" \
  -H "Accept: application/json"</code></pre>
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Available resources include employees, departments, attendance and the dashboard. Send <code class="font-mono">Accept: application/json</code> on every request.</p>
    </div>
</div>
@endsection
