{{-- "To Sign" tab — e-sign document requests + HR documents awaiting signature. --}}
@php $nothingToSign = $toSign->isEmpty() && $pending->isEmpty(); @endphp
<div class="space-y-6">

    @if($nothingToSign)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-500 dark:bg-emerald-500/10 mb-3 mx-auto"><i data-lucide="check-check" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No documents waiting for your signature.</p>
            <p class="text-xs text-slate-400 mt-1">You're all caught up.</p>
        </div>
    @else
        <section>
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Awaiting your signature</h2>
            <div class="space-y-3">
                {{-- e-sign document requests --}}
                @foreach($toSign as $req)
                    <a href="{{ route('documents.sign', $req) }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 flex items-center justify-between gap-4 hover:border-brand-300 transition dark:bg-slate-800 dark:border-slate-700">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-10 w-10 shrink-0 rounded-xl bg-brand-50 text-brand-600 grid place-items-center dark:bg-brand-500/10"><i data-lucide="file-signature" class="h-5 w-5"></i></div>
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 dark:text-white truncate">{{ optional($req->template)->name ?? 'Document' }}</div>
                                <div class="text-xs text-slate-500">Please review &amp; sign</div>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 shrink-0">Sign <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></span>
                    </a>
                @endforeach

                {{-- HR documents --}}
                @foreach($pending as $signer)
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 flex items-center justify-between gap-4 dark:bg-slate-800 dark:border-slate-700">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-10 w-10 shrink-0 rounded-xl bg-amber-50 text-amber-600 grid place-items-center dark:bg-amber-500/10"><i data-lucide="pen-line" class="h-5 w-5"></i></div>
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 dark:text-white truncate">{{ $signer->document->template_name }}</div>
                                <div class="text-xs text-slate-500">Sent {{ optional($signer->document->sent_at)->format('d M Y') ?? $signer->created_at->format('d M Y') }} · signing as {{ ucfirst($signer->role ?? 'signer') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('hr-documents.my-pdf', [$signer->document, 'preview' => 1]) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-4 w-4"></i> Preview</a>
                            <a href="{{ route('hr-documents.sign', $signer->document) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition"><i data-lucide="pen-line" class="h-4 w-4"></i> Review &amp; sign</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- HR signed history (only when HR documents are enabled) --}}
    @if($hrEnabled)
        <section>
            <div class="flex items-center justify-between gap-4 flex-wrap mb-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Signed</h2>
                <form method="GET" action="{{ route('documents-hub.index') }}" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="sign">
                    <label class="text-xs font-semibold text-slate-500">Month</label>
                    <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white text-sm px-3 py-1.5 dark:bg-slate-900 dark:border-slate-600">
                    @if($month)
                        <a href="{{ route('documents-hub.index', ['tab' => 'sign']) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">Clear</a>
                    @endif
                </form>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($done as $signer)
                    <div class="px-5 py-3 flex items-center justify-between gap-4 text-sm">
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $signer->document->template_name }}</div>
                            <div class="text-xs text-slate-500">{{ optional($signer->document->period_start)->format('M Y') }}</div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-semibold"><i data-lucide="check-circle-2" class="h-4 w-4"></i> Signed {{ $signer->signed_at->format('d M Y') }}</span>
                            <a href="{{ route('hr-documents.my-pdf', [$signer->document, 'preview' => 1]) }}" target="_blank" class="rounded-lg p-2 text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition dark:hover:bg-brand-500/10" title="Preview"><i data-lucide="eye" class="h-4 w-4"></i></a>
                            <a href="{{ route('hr-documents.my-pdf', $signer->document) }}" class="rounded-lg p-2 text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition dark:hover:bg-brand-500/10" title="Download PDF"><i data-lucide="download" class="h-4 w-4"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-400">{{ $month ? 'No documents signed in '.\Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y').'.' : 'Nothing signed yet.' }}</div>
                @endforelse
            </div>
        </section>
    @endif
</div>
