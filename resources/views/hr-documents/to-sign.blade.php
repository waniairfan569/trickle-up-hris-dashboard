@extends('layouts.hr-app')

@section('title', 'Documents to sign')
@section('breadcrumb', 'To sign')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="file-signature" class="h-6 w-6 text-brand-500"></i> Documents to sign
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review and sign documents that have been sent to you.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif

    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Awaiting your signature</h2>
        <div class="space-y-3">
            @forelse($pending as $signer)
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 flex items-center justify-between gap-4 dark:bg-slate-800 dark:border-slate-700">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-10 w-10 shrink-0 rounded-xl bg-amber-50 text-amber-600 grid place-items-center dark:bg-amber-500/10"><i data-lucide="pen-line" class="h-5 w-5"></i></div>
                        <div class="min-w-0">
                            <div class="font-bold text-slate-900 dark:text-white truncate">{{ $signer->document->template_name }}</div>
                            <div class="text-xs text-slate-500">Sent {{ optional($signer->document->sent_at)->format('d M Y') ?? $signer->created_at->format('d M Y') }} · signing as {{ ucfirst($signer->role ?? 'signer') }}</div>
                        </div>
                    </div>
                    <a href="{{ route('hr-documents.sign', $signer->document) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition shrink-0"><i data-lucide="pen-line" class="h-4 w-4"></i> Review &amp; sign</a>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-600">Nothing to sign right now. 🎉</div>
            @endforelse
        </div>
    </section>

    @if($done->isNotEmpty())
        <section>
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Signed</h2>
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($done as $signer)
                    <div class="px-5 py-3 flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $signer->document->template_name }}</span>
                        <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-semibold"><i data-lucide="check-circle-2" class="h-4 w-4"></i> Signed {{ $signer->signed_at->format('d M Y') }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
