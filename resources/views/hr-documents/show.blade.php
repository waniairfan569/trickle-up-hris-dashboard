@extends('layouts.hr-app')

@section('title', $document->title ?? $document->template_name)
@section('breadcrumb', 'Documents')

@php
    $data = $document->data ?? [];
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <a href="{{ route('hr-documents.index') }}" class="rounded-lg p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $document->template_name }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ optional($document->employee)->full_name ?? '—' }}
                    @if($document->period_start) · {{ $document->period_start->format('F Y') }} @endif
                    ·
                    @if($document->status === 'completed')
                        <span class="text-emerald-600 font-semibold">Completed</span>
                    @else
                        <span class="text-amber-600 font-semibold">Draft</span>
                    @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('hr-documents.edit', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-4 w-4"></i> Edit</a>
            <a href="{{ route('hr-documents.docx', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="file-text" class="h-4 w-4"></i> Word</a>
            <a href="{{ route('hr-documents.pdf', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="download" class="h-4 w-4"></i> PDF</a>
            <form method="POST" action="{{ route('hr-documents.send', $document) }}" onsubmit="return confirm('Send this document to the employee (and their manager) to sign?')">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition"><i data-lucide="send" class="h-4 w-4"></i> {{ $document->signers->isNotEmpty() ? 'Resend' : 'Send for signature' }}</button>
            </form>
            <form method="POST" action="{{ route('hr-documents.destroy', $document) }}" onsubmit="return confirm('Delete this document permanently?')">
                @csrf @method('DELETE')
                <button class="inline-flex items-center rounded-xl border border-slate-200 p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition dark:border-slate-600 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif

    {{-- Signer status (once sent) --}}
    @if($document->signers->isNotEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Signature status</div>
            <div class="space-y-2">
                @foreach($document->signers as $signer)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ optional($signer->user)->full_name ?? 'User #'.$signer->user_id }} <span class="text-xs font-normal text-slate-400">· {{ ucfirst($signer->role ?? 'signer') }}</span></span>
                        @if($signer->signed_at)
                            <span class="inline-flex items-center gap-1 text-emerald-600 font-semibold"><i data-lucide="check-circle-2" class="h-4 w-4"></i> Signed {{ $signer->signed_at->format('d M Y, H:i') }}</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-amber-600 font-semibold"><i data-lucide="clock" class="h-4 w-4"></i> Awaiting signature</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @include('hr-documents._readonly', ['document' => $document, 'data' => $data])
</div>
@endsection
