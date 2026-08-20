@extends('layouts.hr-app')

@section('title', $document->title ?? $document->template_name)
@section('breadcrumb', 'Documents')

@php
    $data = $document->data ?? [];
    $sigFields = collect($document->signatureFields());
    $hasManagerField = $sigFields->contains(fn ($f) => \Illuminate\Support\Str::contains(\Illuminate\Support\Str::lower($f['label'] ?? ''), 'manager'));
    $defaultManagerId = optional($document->employee)->manager_id;
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{ sendOpen: false, includeManager: {{ $hasManagerField && $defaultManagerId ? 'true' : 'false' }} }">

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
            <a href="{{ route('hr-documents.pdf', [$document, 'preview' => 1]) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-4 w-4"></i> Preview</a>
            <a href="{{ route('hr-documents.docx', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="file-text" class="h-4 w-4"></i> Word</a>
            <a href="{{ route('hr-documents.pdf', $document) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="download" class="h-4 w-4"></i> PDF</a>
            <button type="button" @click="sendOpen = true" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition"><i data-lucide="send" class="h-4 w-4"></i> {{ $document->signers->isNotEmpty() ? 'Resend' : 'Send for signature' }}</button>
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

    {{-- Send-for-signature dialog --}}
    <div x-show="sendOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-slate-900/50" @click="sendOpen = false"></div>
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl dark:bg-slate-800" @click.stop>
            <form method="POST" action="{{ route('hr-documents.send', $document) }}">
                @csrf
                <div class="p-5 border-b border-slate-100 dark:border-slate-700">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="send" class="h-5 w-5 text-brand-500"></i> Send for signature</h3>
                    <p class="text-xs text-slate-500 mt-1">Choose who needs to sign this document.</p>
                </div>

                <div class="p-5 space-y-4">
                    {{-- Employee (always) --}}
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <div class="h-9 w-9 shrink-0 rounded-lg bg-brand-50 text-brand-600 grid place-items-center dark:bg-brand-500/10"><i data-lucide="user" class="h-4 w-4"></i></div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ optional($document->employee)->full_name ?? '—' }}</div>
                            <div class="text-xs text-slate-500">Employee — signs the Employee section</div>
                        </div>
                    </div>

                    @if($hasManagerField)
                        {{-- Manager (optional) --}}
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input type="checkbox" name="include_manager" value="1" x-model="includeManager" class="rounded text-brand-500 border-slate-300 focus:ring-brand-500">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Also require the line manager to sign</span>
                        </label>

                        <div x-show="includeManager" x-cloak class="pl-7">
                            <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Line manager (signs the Manager section)</label>
                            <select name="manager_id" :disabled="!includeManager" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600">
                                <option value="">— Select manager —</option>
                                @foreach($employees as $emp)
                                    @if(!$document->employee || $emp['id'] != $document->employee->id)
                                        <option value="{{ $emp['id'] }}" @selected($defaultManagerId == $emp['id'])>{{ $emp['name'] }} · {{ $emp['department'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @unless($defaultManagerId)
                                <p class="text-xs text-amber-600 mt-1.5">This employee has no line manager set — pick one above.</p>
                            @endunless
                        </div>
                    @endif
                </div>

                <div class="p-5 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-3">
                    <button type="button" @click="sendOpen = false" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition"><i data-lucide="send" class="h-4 w-4"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
