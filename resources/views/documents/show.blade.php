@extends('layouts.hr-app')

@section('title', 'Document')
@section('breadcrumb', 'Documents')

@php
    $badge = [
        'in_progress' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'declined' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'cancelled' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    ][$documentRequest->status] ?? 'bg-slate-100 text-slate-600';
    $statusLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined', 'cancelled' => 'Cancelled'][$documentRequest->status] ?? ucfirst($documentRequest->status);
    $auth = auth()->user();
    $awaitingMe = $documentRequest->isAwaiting($auth);
@endphp

@section('content')
<style>[x-cloak]{display:none!important}</style>
<script>
    window.__signedDataUrl = "{{ route('documents.signed-data', $documentRequest) }}";
    window.__signedName = @json(($documentRequest->template->name ?? 'document'));
</script>
<script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>

<div class="max-w-3xl mx-auto space-y-6" x-data="signedDownloader()">
    <div class="flex items-center gap-3">
        <a href="{{ route('documents.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white truncate">{{ $documentRequest->template->name ?? 'Document' }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">For {{ $documentRequest->subject->full_name ?? '—' }} · sent by {{ $documentRequest->creator->full_name ?? '—' }}</p>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $badge }}">{{ $statusLabel }}</span>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i><span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Actions -->
    <div class="flex flex-wrap gap-2">
        @if($awaitingMe)
            <a href="{{ route('documents.sign', $documentRequest) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                <i data-lucide="pen-tool" class="h-4 w-4"></i> Sign now
            </a>
        @endif
        @if($documentRequest->status === 'completed')
            <button type="button" @click="viewSigned()" :disabled="busy" :class="busy && 'opacity-50'"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-500/20 hover:bg-emerald-700">
                <template x-if="!busy"><i data-lucide="eye" class="h-4 w-4"></i></template>
                <template x-if="busy"><i data-lucide="loader" class="h-4 w-4 animate-spin"></i></template>
                <span x-text="busy ? 'Preparing…' : 'View signed document'"></span>
            </button>
            <button type="button" @click="download()" :disabled="busy" :class="busy && 'opacity-50'"
                    class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                <i data-lucide="file-down" class="h-4 w-4"></i>
                <span>Download signed PDF</span>
            </button>
        @endif
        <a href="{{ route('documents.file', $documentRequest) }}" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300">
            <i data-lucide="file-text" class="h-4 w-4"></i> View original (blank)
        </a>
    </div>
    <p x-show="err" x-cloak class="text-sm text-rose-600" x-text="err"></p>

    <!-- Signers / progress -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">Signers</span></div>
        @foreach($documentRequest->signers as $s)
            @php
                $isCurrent = $documentRequest->status === 'in_progress' && $documentRequest->currentSigner() && $documentRequest->currentSigner()->id === $s->id;
                $dot = $s->status === 'signed' ? 'bg-emerald-500' : ($s->status === 'declined' ? 'bg-rose-500' : ($isCurrent ? 'bg-amber-500' : 'bg-slate-300'));
            @endphp
            <div class="flex items-center gap-3 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-700">{{ $loop->iteration }}</span>
                <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-white truncate">{{ $s->user->full_name ?? 'Unknown' }} <span class="text-xs font-normal text-slate-400">· {{ $s->role_label }}</span></p>
                    <p class="text-xs text-slate-400">
                        @if($s->status === 'signed') Signed {{ optional($s->signed_at)->format('d M Y, H:i') }}
                        @elseif($s->status === 'declined') Declined {{ optional($s->signed_at)->format('d M Y, H:i') }}
                        @elseif($isCurrent) Awaiting signature
                        @else Waiting @endif
                    </p>
                </div>
                @if($s->status === 'signed' && $s->signature_image)
                    <img src="{{ $s->signature_image }}" alt="signature" class="h-8 max-w-[120px] object-contain rounded border border-slate-200 bg-white dark:border-slate-600">
                @endif
            </div>
        @endforeach
    </div>

    <!-- Audit trail -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">Activity</span></div>
        <div class="px-6 py-4 space-y-3">
            @forelse($documentRequest->events as $e)
                <div class="flex items-start gap-3 text-sm">
                    <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-700"><i data-lucide="dot" class="h-4 w-4"></i></span>
                    <div>
                        <p class="text-slate-700 dark:text-slate-200">
                            <span class="font-semibold capitalize">{{ $e->action }}</span>
                            @if($e->user) by {{ $e->user->full_name }} @endif
                            @if($e->detail) <span class="text-slate-400">— {{ $e->detail }}</span> @endif
                        </p>
                        <p class="text-xs text-slate-400">{{ optional($e->created_at)->format('d M Y, H:i') }} @if($e->ip) · {{ $e->ip }} @endif</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No activity yet.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    function signedDownloader() {
        return {
            busy: false,
            err: '',
            // Build the signed PDF (original + placed field values + signatures) and
            // return a blob URL. Shared by both View and Download.
            async buildSignedPdfUrl() {
                if (!window.PDFLib) throw new Error('PDF engine failed to load.');
                const data = await fetch(window.__signedDataUrl, { headers: { 'Accept': 'application/json' } }).then(r => r.json());
                const bytes = await fetch(data.fileUrl).then(r => r.arrayBuffer());
                const { PDFDocument, StandardFonts, rgb } = window.PDFLib;
                const pdf = await PDFDocument.load(bytes);
                const font = await pdf.embedFont(StandardFonts.Helvetica);
                const pages = pdf.getPages();
                for (const f of data.fields) {
                    const page = pages[f.page - 1];
                    if (!page) continue;
                    const pw = page.getWidth(), ph = page.getHeight();
                    if ((f.type === 'signature' || f.type === 'initials')) {
                        if (!f.image) continue;
                        const png = await pdf.embedPng(f.image);
                        const boxW = f.w * pw, boxH = (f.h || 0.05) * ph;
                        const scale = Math.min(boxW / png.width, boxH / png.height);
                        const w = png.width * scale, h = png.height * scale;
                        const topY = ph - f.y * ph;
                        page.drawImage(png, { x: f.x * pw, y: topY - h, width: w, height: h });
                        if (f.name) {
                            const ns = Math.max(6, Math.min(9, boxH * 0.32));
                            page.drawText(String(f.name), { x: f.x * pw, y: topY - h - ns - 1, size: ns, font, color: rgb(0.45, 0.45, 0.5) });
                        }
                        continue;
                    }
                    if (!f.value) continue;
                    const size = Math.max(8, Math.min(13, (f.h || 0.03) * ph * 0.7));
                    page.drawText(String(f.value), { x: f.x * pw + 2, y: ph - f.y * ph - size, size, font, color: rgb(0.1, 0.1, 0.12) });
                }
                const out = await pdf.save();
                return URL.createObjectURL(new Blob([out], { type: 'application/pdf' }));
            },
            async viewSigned() {
                if (this.busy) return;
                this.busy = true; this.err = '';
                // Open the tab synchronously so the browser doesn't block the popup,
                // then point it at the generated PDF once ready.
                const tab = window.open('', '_blank');
                try {
                    const url = await this.buildSignedPdfUrl();
                    if (tab) { tab.location = url; } else { window.location = url; }
                    setTimeout(() => URL.revokeObjectURL(url), 60000);
                } catch (e) {
                    if (tab) tab.close();
                    this.err = (e && e.message) ? e.message : 'Could not build the signed PDF.';
                } finally { this.busy = false; }
            },
            async download() {
                if (this.busy) return;
                this.busy = true; this.err = '';
                try {
                    const url = await this.buildSignedPdfUrl();
                    const a = document.createElement('a');
                    a.href = url; a.download = (window.__signedName || 'document').replace(/[^\w.\- ]+/g, '') + ' — signed.pdf';
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 10000);
                } catch (e) {
                    this.err = (e && e.message) ? e.message : 'Could not build the signed PDF.';
                } finally { this.busy = false; }
            },
        };
    }
</script>
@endsection
