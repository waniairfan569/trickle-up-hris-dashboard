@extends('layouts.hr-app')

@section('title', 'My Code Requests')
@section('breadcrumb', 'My Code Requests')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="key-round" class="h-6 w-6 text-brand-500"></i> My Code Requests
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Codes you asked HR to share for company tools.</p>
    </div>

    <div class="space-y-3">
        @forelse($requests as $req)
            <div x-data="{ cancelling: false, cancelled: false, err: '' }" x-show="!cancelled" x-transition
                 class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-extrabold text-slate-900 dark:text-white">{{ $req->tool_name }}</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Requested {{ $req->created_at->diffForHumans() }} · {{ $req->request_number }}</p>
                        @if($req->message)<p class="text-xs text-slate-500 dark:text-slate-400 mt-1 italic">"{{ $req->message }}"</p>@endif
                    </div>
                    @if($req->status === 'code_sent')
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 shrink-0">✅ Received</span>
                    @elseif($req->status === 'rejected')
                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-500/10 shrink-0">🚫 Declined</span>
                    @elseif($req->status === 'cancelled')
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300 shrink-0">✋ Cancelled</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-500/10 shrink-0">⏳ Pending</span>
                    @endif
                </div>

                @if($req->status === 'rejected')
                    <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-500/30 dark:bg-rose-500/10">
                        <p class="text-xs font-bold text-rose-700 dark:text-rose-400">This request was declined by HR.</p>
                        @if($req->rejection_reason)<p class="text-xs text-rose-600 dark:text-rose-300 mt-1">Reason: {{ $req->rejection_reason }}</p>@endif
                    </div>
                @endif

                @if($req->status === 'code_sent')
                    <div class="mt-4 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-4 text-center dark:border-slate-600 dark:bg-slate-900/40">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Your code</div>
                        <div class="font-mono text-3xl font-extrabold tracking-widest text-slate-900 dark:text-white mt-1 break-all">{{ $req->code_provided }}</div>
                        @if($req->code_expires_note)<p class="text-xs font-bold text-rose-600 mt-2">⏱ {{ $req->code_expires_note }}</p>@endif
                        <p class="text-[11px] text-slate-400 mt-2">Sent {{ optional($req->code_sent_at)->diffForHumans() }} by {{ optional($req->responder)->full_name ?? 'HR' }}</p>
                    </div>
                @endif

                @if($req->status === 'pending')
                    <div class="mt-4 flex items-center justify-end">
                        <button type="button" :disabled="cancelling"
                                @click="cancelling = true; err = '';
                                    fetch('{{ url('code-requests') }}/{{ $req->id }}/cancel', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
                                        .then(r => r.json())
                                        .then(d => { if (d.success) { cancelled = true; } else { cancelling = false; err = d.message || 'Could not cancel.'; } })
                                        .catch(() => { cancelling = false; err = 'Network error. Try again.'; })"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 disabled:opacity-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300">
                            <i data-lucide="x" class="h-4 w-4"></i><span x-text="cancelling ? 'Cancelling…' : 'Cancel request'"></span>
                        </button>
                    </div>
                    <p x-show="err" x-cloak class="text-xs text-rose-600 mt-2 text-right" x-text="err"></p>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="key-round" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No code requests yet</p>
                <p class="text-xs text-slate-400 mt-1">Use the “Need a login code?” card on your dashboard.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
