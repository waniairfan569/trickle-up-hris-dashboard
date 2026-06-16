@extends('layouts.hr-app')

@section('title', 'Documents')
@section('breadcrumb', 'Documents')

@php
    $badge = fn ($status) => [
        'in_progress' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'declined' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'cancelled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ][$status] ?? 'bg-slate-100 text-slate-600';
    $label = fn ($status) => ['in_progress' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined', 'cancelled' => 'Cancelled'][$status] ?? ucfirst($status);
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Documents</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Documents awaiting your signature and ones you're involved in.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i><span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Awaiting my signature -->
    @if($toSign->count())
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-amber-500/30">
            <div class="px-6 py-3 bg-amber-50 dark:bg-amber-500/10 border-b border-amber-100 dark:border-amber-500/20">
                <span class="text-sm font-bold text-amber-800 dark:text-amber-300">Awaiting your signature ({{ $toSign->count() }})</span>
            </div>
            @foreach($toSign as $req)
                <div class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="file-signature" class="h-5 w-5"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $req->template->name ?? 'Document' }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">For {{ $req->subject->full_name ?? '—' }} · sent {{ $req->created_at->diffForHumans() }}</p>
                    </div>
                    <a href="{{ route('documents.sign', $req) }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                        <i data-lucide="pen-tool" class="h-3.5 w-3.5"></i> Sign now
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <!-- All documents -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60">
            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">All documents</span>
        </div>
        @forelse($all as $req)
            <a href="{{ route('documents.show', $req) }}" class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 last:border-0 hover:bg-slate-50 dark:border-slate-700/60 dark:hover:bg-slate-700/30">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-700"><i data-lucide="file-text" class="h-4 w-4"></i></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $req->template->name ?? 'Document' }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                        For {{ $req->subject->full_name ?? '—' }} · {{ $req->signers->where('status', 'signed')->count() }}/{{ $req->signers->count() }} signed · {{ $req->created_at->format('d M Y') }}
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge($req->status) }}">{{ $label($req->status) }}</span>
            </a>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="inbox" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No documents yet</p>
                <p class="text-xs text-slate-400 mt-1">Documents sent for signature will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
