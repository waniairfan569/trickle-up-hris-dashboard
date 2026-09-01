@extends('layouts.operator')

@section('title', 'Operators')
@section('breadcrumb', 'Operators')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ addOpen:false }">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="shield" class="h-6 w-6 text-indigo-500"></i> Operators
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Who can run the platform. <b>Owners</b> have full power; <b>Support</b> can view &amp; impersonate to help, but can’t change pricing, suspend/cancel, or manage operators.</p>
        </div>
        <button type="button" @click="addOpen=true" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700"><i data-lucide="user-plus" class="h-4 w-4"></i> Add operator</button>
    </div>

    {{-- Operators list --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @foreach($operators as $op)
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                    <div class="h-9 w-9 grid place-items-center rounded-full bg-indigo-600 text-white text-xs font-bold">{{ $op->initials }}</div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800 dark:text-white">{{ $op->full_name }} @if($op->id===auth()->id())<span class="text-[11px] font-normal text-slate-400">(you)</span>@endif</p>
                        <p class="text-[11px] text-slate-400">{{ $op->email }}</p>
                    </div>
                    @if($op->hasTwoFactorEnabled())
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400" title="2FA on"><i data-lucide="shield-check" class="h-3 w-3"></i> 2FA</span>
                    @endif
                    @if($op->operator_role==='support')
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300"><i data-lucide="headphones" class="h-3 w-3"></i> Support</span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-bold text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"><i data-lucide="crown" class="h-3 w-3"></i> Owner</span>
                    @endif
                    <div class="flex items-center gap-1.5">
                        <form action="{{ route('operator.operators.role', $op) }}" method="POST">@csrf @method('PUT')
                            <input type="hidden" name="operator_role" value="{{ $op->operator_role==='owner' ? 'support' : 'owner' }}">
                            <button class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Make {{ $op->operator_role==='owner' ? 'Support' : 'Owner' }}</button>
                        </form>
                        @if($op->hasTwoFactorEnabled() && $op->id!==auth()->id())
                            <form action="{{ route('operator.operators.reset-2fa', $op) }}" method="POST" onsubmit="return confirm('Reset 2FA for {{ $op->full_name }}? They can set it up again on next login.')">@csrf
                                <button title="Reset 2FA (lockout recovery)" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10"><i data-lucide="shield-off" class="h-4 w-4"></i></button>
                            </form>
                        @endif
                        @if($op->id!==auth()->id())
                            <form action="{{ route('operator.operators.revoke', $op) }}" method="POST" onsubmit="return confirm('Revoke operator access for {{ $op->full_name }}?')">@csrf @method('DELETE')
                                <button title="Revoke" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"><i data-lucide="user-minus" class="h-4 w-4"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Audit --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/60"><h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Operator activity</h2></div>
        <div class="divide-y divide-slate-50 dark:divide-slate-700/40">
            @forelse($audit as $a)
                <div class="flex items-center gap-3 px-5 py-3">
                    <span class="grid place-items-center h-8 w-8 shrink-0 rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300"><i data-lucide="{{ $a->icon }}" class="h-4 w-4"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-800 dark:text-white">{{ $a->description }}</p>
                        <p class="text-[11px] text-slate-400">{{ $a->created_at->format('d M Y · H:i') }}@if($a->operator) · {{ $a->operator->full_name }}@endif @if($a->ip)· {{ $a->ip }}@endif</p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-400">No operator activity yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Add operator modal --}}
    <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4" @keydown.escape.window="addOpen=false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" @click.away="addOpen=false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="user-plus" class="h-5 w-5 text-indigo-500"></i> Add operator</h3>
            <form action="{{ route('operator.operators.store') }}" method="POST" class="mt-4 space-y-3">@csrf
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-[11px] font-bold text-slate-500 mb-1">First name</label><input type="text" name="first_name" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                    <div><label class="block text-[11px] font-bold text-slate-500 mb-1">Last name</label><input type="text" name="last_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                </div>
                <div><label class="block text-[11px] font-bold text-slate-500 mb-1">Email</label><input type="email" name="email" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-[11px] font-bold text-slate-500 mb-1">Temporary password</label><input type="text" name="password" required minlength="8" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-[11px] font-bold text-slate-500 mb-1">Role</label>
                    <select name="operator_role" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="support">Support — view &amp; impersonate only</option>
                        <option value="owner">Owner — full power</option>
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" @click="addOpen=false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700">Add operator</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
