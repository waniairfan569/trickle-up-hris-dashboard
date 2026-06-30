@extends('layouts.hr-app')

@section('title', 'Active Sessions')
@section('breadcrumb', 'Administration > Active Sessions')

@section('content')
@php $isSuper = auth()->user()->hasRole('super_admin'); @endphp
<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="monitor-smartphone" class="h-6 w-6 text-brand-500"></i> Active Sessions
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $sessions->count() }} active session(s). Sessions last 360 days.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 text-slate-500 font-medium uppercase text-xs dark:bg-slate-900/40">
                    <tr>
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">IP Address</th>
                        <th class="px-5 py-3">Device / Browser</th>
                        <th class="px-5 py-3">Last Active</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($sessions as $s)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                            <td class="px-5 py-3">
                                @if($s['user'])
                                    <span class="font-bold text-slate-800 dark:text-white">{{ trim($s['user']->first_name . ' ' . $s['user']->last_name) }}</span>
                                    @if($s['is_current'])<span class="ml-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">You</span>@endif
                                    <div class="text-xs text-slate-400">{{ $s['user']->email }}</div>
                                @else
                                    <span class="text-slate-400 italic">Guest / not signed in</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $s['ip'] ?: '—' }}</td>
                            <td class="px-5 py-3">{{ $s['device'] }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">{{ $s['last_active']->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @unless($s['is_current'])
                                        <form method="POST" action="{{ route('admin.sessions.revoke', $s['id']) }}" onsubmit="return confirm('Revoke this session? That device will be signed out.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-rose-600">Revoke</button>
                                        </form>
                                    @endunless
                                    @if($s['user'] && !$s['is_current'])
                                        <form method="POST" action="{{ route('admin.sessions.revoke-all', $s['user_id']) }}" onsubmit="return confirm('Sign {{ trim($s['user']->first_name . ' ' . $s['user']->last_name) }} out from ALL devices?');">
                                            @csrf
                                            <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800">Logout all devices</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No active sessions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($isSuper)
        <!-- Danger zone -->
        <div class="bg-white rounded-2xl border border-rose-200 shadow-sm p-6 dark:bg-slate-800 dark:border-rose-500/30"
             x-data="{ open: false, text: '' }">
            <h2 class="text-sm font-bold text-rose-700 flex items-center gap-2"><i data-lucide="alert-triangle" class="h-4 w-4"></i> Danger zone</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xl">Force logout <b>everyone</b> (except you) from every device. Use only for a security incident or major policy change. Type <b>CONFIRM</b> to enable.</p>
            <form method="POST" action="{{ route('admin.sessions.force-logout-everyone') }}" @submit="return text === 'CONFIRM'" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type CONFIRM</label>
                    <input type="text" name="confirm" x-model="text" autocomplete="off" class="rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <button type="submit" :disabled="text !== 'CONFIRM'" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed">Force logout everyone</button>
            </form>
        </div>
    @endif
</div>
@endsection
