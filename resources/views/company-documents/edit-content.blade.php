@extends('layouts.hr-app')

@section('title', 'Edit Document Text')
@section('breadcrumb', 'Company Documents')

@section('content')
<div class="max-w-5xl mx-auto space-y-4">
    <a href="{{ route('company-documents.edit', $document) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> Back to details</a>

    @if($document->template)
        @include('company-documents.partials.builder-steps', ['template' => $document->template, 'current' => 1])
    @endif

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
        <div class="px-5 pt-4 pb-3 border-b border-slate-100 dark:border-slate-700/60 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-base font-bold text-slate-900 dark:text-white">{{ $document->title }}</h1>
                <p class="text-xs text-slate-400 mt-0.5">Edit the text just like in Word — type or insert <span class="font-semibold">[tokens]</span> where employee data should go. When you’re done, convert to PDF and place your fields.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" form="convertOriginalForm" title="Keeps letterhead, watermark and floating images exactly as in Word, and carries over the tokens you inserted from the dropdown" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                    <i data-lucide="image" class="h-4 w-4"></i> Keep Word layout + tokens
                </button>
                <button type="submit" form="convertForm" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">
                    <i data-lucide="file-check" class="h-4 w-4"></i> Convert edited text to PDF
                </button>
            </div>
        </div>
        <div class="px-5 py-3 border-b border-sky-100 bg-sky-50/70 text-[11px] text-sky-800 dark:bg-sky-500/10 dark:border-sky-500/20 dark:text-sky-300">
            <p class="font-bold flex items-center gap-1.5"><i data-lucide="lightbulb" class="h-3.5 w-3.5"></i> Which button should I use?</p>
            <ul class="mt-1.5 space-y-1 list-disc pl-5">
                <li><span class="font-bold">Branded document</span> (letterhead / watermark / logo): use <span class="font-bold">“Keep Word layout + tokens”</span>. It converts your original Word file untouched, so the design is pixel-perfect. Add each token with the <span class="font-bold">“Insert token”</span> dropdown — click in the document exactly where the value goes first, then pick the token.
                <span class="block text-sky-600 dark:text-sky-400">Most reliable of all: type the tokens straight into Word (e.g. put <code>[Employee Signature]</code> on the signature line), upload, and use this button — the tokens are guaranteed to land where you typed them.</span></li>
                <li><span class="font-bold">Plain, unbranded document</span>: edit the text freely below and use <span class="font-bold">“Convert edited text to PDF”</span>. This re-flows the page like a web document, so don’t use it for anything with a letterhead or watermark.</li>
            </ul>
        </div>
        @if(!empty($missingFonts))
            <div class="px-5 py-3 border-b border-amber-100 bg-amber-50/80 text-[11px] text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300 flex items-start gap-1.5">
                <i data-lucide="type" class="h-3.5 w-3.5 shrink-0 mt-0.5"></i>
                <span>
                    <span class="font-bold">Heads up — the server is missing {{ count($missingFonts) === 1 ? 'a font' : 'fonts' }} this document uses:</span>
                    <span class="font-bold">{{ implode(', ', $missingFonts) }}</span>. LibreOffice will substitute {{ count($missingFonts) === 1 ? 'it' : 'them' }} with a similar font, so spacing may shift slightly in the PDF.
                    <span class="block mt-1">To match Word exactly, ask your host to install {{ count($missingFonts) === 1 ? 'it' : 'them' }} (copy the <code>.ttf</code> to <code>/usr/share/fonts</code>, then <code>fc-cache&nbsp;-f</code>), or simply set the document’s font to <span class="font-bold">Arial, Times New Roman or Calibri</span> in Word — those are already covered on the server.</span>
                </span>
            </div>
        @endif

        {{-- Toolbar --}}
        <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/30 flex flex-wrap items-center gap-1">
            @php $tb = 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-200/70 dark:text-slate-300 dark:hover:bg-slate-700'; @endphp
            <button type="button" class="{{ $tb }}" onclick="cmd('undo')" title="Undo"><i data-lucide="undo-2" class="h-4 w-4"></i></button>
            <button type="button" class="{{ $tb }}" onclick="cmd('redo')" title="Redo"><i data-lucide="redo-2" class="h-4 w-4"></i></button>
            <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700"></span>
            <button type="button" class="{{ $tb }}" onclick="cmd('bold')" title="Bold"><i data-lucide="bold" class="h-4 w-4"></i></button>
            <button type="button" class="{{ $tb }}" onclick="cmd('italic')" title="Italic"><i data-lucide="italic" class="h-4 w-4"></i></button>
            <button type="button" class="{{ $tb }}" onclick="cmd('underline')" title="Underline"><i data-lucide="underline" class="h-4 w-4"></i></button>
            <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700"></span>
            <button type="button" class="{{ $tb }}" onclick="cmd('insertUnorderedList')" title="Bullet list"><i data-lucide="list" class="h-4 w-4"></i></button>
            <button type="button" class="{{ $tb }}" onclick="cmd('insertOrderedList')" title="Numbered list"><i data-lucide="list-ordered" class="h-4 w-4"></i></button>
            <span class="mx-1 h-5 w-px bg-slate-200 dark:bg-slate-700"></span>
            <select onchange="insertToken(this.value); this.selectedIndex = 0;" class="h-8 rounded-lg border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-900 dark:border-slate-600 dark:text-slate-300">
                <option value="">Insert token…</option>
                @foreach($tokenGroups as $group => $tokens)
                    <optgroup label="{{ $group }}">
                        @foreach($tokens as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            <span class="ml-auto text-[11px] text-slate-400 hidden sm:block">Tokens auto-fill with each employee’s real data when the document is sent.</span>
        </div>

        {{-- Editable document --}}
        <iframe id="docFrame" class="w-full" style="height: 72vh; background: #eef2f7;"></iframe>
    </div>

    <form method="POST" action="{{ route('company-documents.convert', $document) }}" id="convertForm">
        @csrf
        <input type="hidden" name="html" id="htmlInput">
    </form>
    <form method="POST" action="{{ route('company-documents.convert-original', $document) }}" id="convertOriginalForm">
        @csrf
        <input type="hidden" name="tokens" id="tokensInput">
    </form>

    <div class="flex justify-end gap-2">
        <button type="submit" form="convertOriginalForm" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
            <i data-lucide="image" class="h-4 w-4"></i> Keep Word layout + tokens
        </button>
        <button type="submit" form="convertForm" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">
            <i data-lucide="file-check" class="h-4 w-4"></i> Convert edited text to PDF
        </button>
    </div>
</div>

<script>
    const frame = document.getElementById('docFrame');
    const rawHtml = {!! json_encode($html) !!};

    // Editor-only chrome (page look) — removed again before submitting, so it
    // never leaks into the PDF.
    const chromeCss = 'html{background:#eef2f7 !important;} body{max-width:820px;margin:24px auto !important;padding:48px 56px !important;background:#fff !important;box-shadow:0 1px 8px rgba(15,23,42,.12);border-radius:6px;min-height:60vh;caret-color:#0f172a;}';

    // Always grab the CURRENT iframe document — srcdoc navigation replaces it,
    // so a cached reference (or an early document.write) ends up pointing at a
    // dead document and the page looks empty.
    function fdoc() { return frame.contentDocument; }

    function attachChrome(d) {
        if (!d || d.getElementById('editor-chrome')) return;
        const s = d.createElement('style');
        s.id = 'editor-chrome';
        s.textContent = chromeCss;
        (d.head || d.documentElement).appendChild(s);
    }

    frame.addEventListener('load', () => {
        const d = fdoc();
        if (!d) return;
        d.designMode = 'on';
        attachChrome(d);
    });
    frame.srcdoc = rawHtml;

    // Tokens the admin inserts, with the text right before the caret — lets the
    // server drop the same tokens into the original Word file for "convert
    // as-is", so branding AND tokens survive together.
    const insertedTokens = [];

    function cmd(name) {
        frame.contentWindow.focus();
        fdoc().execCommand(name, false, null);
    }

    // The label/text right before the caret — the anchor the server matches
    // inside the .docx. We climb to the containing block so a caret sitting on
    // a blank underline still picks up the label before it (e.g. "Signature:"),
    // and trailing blanks / underscores / dashes are trimmed off so the anchor
    // ends on real text.
    const cleanAnchor = t => (t || '').replace(/\s+/g, ' ').replace(/[\s_.·•–—-]+$/, '').trim();
    const meaningful = t => t.replace(/[^A-Za-z0-9]/g, '').length;

    function currentAnchor() {
        try {
            const sel = fdoc().getSelection();
            if (!sel || !sel.rangeCount) return '';
            const r = sel.getRangeAt(0);

            let block = r.startContainer;
            while (block && block.nodeType === 3) block = block.parentNode;
            const blockEl = (block && block.closest)
                ? (block.closest('td,th,li,p,div,h1,h2,h3,h4') || block) : block;

            // Text of the current block up to the caret.
            let text = '';
            if (blockEl) {
                const pre = r.cloneRange();
                pre.selectNodeContents(blockEl);
                pre.setEnd(r.startContainer, r.startOffset);
                text = pre.toString();
            } else if (r.startContainer.nodeType === 3) {
                text = r.startContainer.textContent.slice(0, r.startOffset);
            }

            let anchor = cleanAnchor(text);
            // A caret on a blank line / empty table cell has no label to anchor
            // to — walk back to preceding cells/blocks to borrow the label
            // (e.g. "Signature:" sitting in the cell before the blank).
            let node = blockEl, guard = 0;
            while (meaningful(anchor) < 3 && node && guard++ < 5) {
                node = node.previousElementSibling
                    || (node.parentElement ? node.parentElement.previousElementSibling : null);
                if (!node) break;
                anchor = cleanAnchor((node.textContent || '') + ' ' + text);
            }
            return anchor.slice(-60);
        } catch (e) { return ''; }
    }

    function insertToken(token) {
        if (!token) return;
        frame.contentWindow.focus();
        const anchor = currentAnchor();
        const d = fdoc();
        const sel = d.getSelection();

        // Insert the token text node directly at the caret — reliable across
        // browsers, unlike execCommand('insertText') which silently no-ops when
        // the caret isn't inside the iframe (that lost the token in the edited
        // PDF while the as-is path still had it from the anchor list).
        if (sel && sel.rangeCount) {
            const r = sel.getRangeAt(0);
            r.deleteContents();
            const node = d.createTextNode(token);
            r.insertNode(node);
            r.setStartAfter(node);
            r.collapse(true);
            sel.removeAllRanges();
            sel.addRange(r);
        } else {
            // No caret in the document yet → append so it's never lost.
            (d.body || d.documentElement).appendChild(d.createTextNode(' ' + token));
        }

        if (anchor) insertedTokens.push({ anchor, token });
    }

    document.getElementById('convertOriginalForm').addEventListener('submit', () => {
        // Send tokens in DOCUMENT order (by where their anchor sits) so the
        // server's forward cursor drops each one at the right spot.
        const plain = (fdoc().body ? fdoc().body.innerText : '').replace(/\s+/g, ' ');
        const ordered = insertedTokens
            .map(t => ({ ...t, _pos: plain.indexOf(t.anchor.replace(/\s+/g, ' ')) }))
            .sort((a, b) => (a._pos < 0 ? 1e9 : a._pos) - (b._pos < 0 ? 1e9 : b._pos))
            .map(({ anchor, token }) => ({ anchor, token }));
        document.getElementById('tokensInput').value = JSON.stringify(ordered);
    });

    document.getElementById('convertForm').addEventListener('submit', () => {
        const d = fdoc();
        const c = d.getElementById('editor-chrome');
        if (c) c.remove();
        document.getElementById('htmlInput').value = '<!DOCTYPE html>' + d.documentElement.outerHTML;
        // Re-attach in case validation bounces the user back without reload.
        attachChrome(d);
    });
</script>
@endsection
