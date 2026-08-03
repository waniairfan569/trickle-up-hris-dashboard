@extends('layouts.hr-app')

@section('title', 'Code Requests')
@section('breadcrumb', 'Code Requests')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="key-round" class="h-6 w-6 text-brand-500"></i> Code Requests
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Employees waiting for a login/OTP code sent to the company email. Respond fast — they’re stuck.</p>
    </div>

    {{-- Pending --}}
    <div class="space-y-3" id="pending-list">
        @forelse($pending as $req)
            <div id="code-req-{{ $req->id }}" data-req-id="{{ $req->id }}"
                 class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 dark:bg-slate-800 dark:border-amber-500/30"
                 x-data="codeCard({{ $req->id }})" x-show="!done" x-transition>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-extrabold text-slate-900 dark:text-white">
                            {{ optional($req->employee)->full_name ?? 'Employee' }} needs a <span class="text-brand-600">{{ $req->tool_name }}</span> code
                        </p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Requested {{ $req->created_at->diffForHumans() }} · {{ $req->request_number }}</p>
                        @if($req->message)<p class="text-xs text-slate-500 dark:text-slate-400 mt-1 italic">"{{ $req->message }}"</p>@endif
                    </div>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-500/10 shrink-0">⏳ Pending</span>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row gap-2">
                    <input type="text" x-model="code" placeholder="Paste the code here" class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono font-bold dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <input type="text" x-model="note" placeholder="Note (e.g. Valid 10 min)" class="sm:w-44 rounded-xl border border-slate-300 px-3 py-2.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <button type="button" @click="send()" :disabled="sending || rejecting"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                        <i data-lucide="send" class="h-4 w-4"></i><span x-text="sending ? 'Sending…' : 'Send'"></span>
                    </button>
                    <button type="button" @click="showReject = !showReject" :disabled="sending || rejecting"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100 disabled:opacity-50 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400">
                        <i data-lucide="x" class="h-4 w-4"></i> Decline
                    </button>
                </div>

                {{-- Decline reason (revealed) --}}
                <div x-show="showReject" x-cloak x-transition class="mt-2 flex flex-col sm:flex-row gap-2">
                    <input type="text" x-model="reason" placeholder="Reason (optional — shown to the employee)"
                           class="flex-1 rounded-xl border border-rose-200 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                    <button type="button" @click="reject()" :disabled="rejecting"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50">
                        <i data-lucide="ban" class="h-4 w-4"></i><span x-text="rejecting ? 'Declining…' : 'Confirm decline'"></span>
                    </button>
                </div>

                <p x-show="error" x-cloak class="text-xs text-rose-600 mt-2" x-text="error"></p>
                <div x-show="done" x-cloak class="text-sm font-bold mt-2" :class="rejected ? 'text-rose-700' : 'text-emerald-700'" x-text="(rejected ? '🚫 ' : '✅ ') + doneMsg"></div>
            </div>
        @empty
            <div id="pending-empty" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="check-check" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">All caught up</p>
                <p class="text-xs text-slate-400 mt-1">No employees waiting for a code right now.</p>
            </div>
        @endforelse
    </div>

    {{-- Resolved (collapsible) --}}
    @if($resolved->count())
        <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                <span>Recently sent ({{ $resolved->count() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" x-cloak class="divide-y divide-slate-100 dark:divide-slate-700/60 border-t border-slate-100 dark:border-slate-700/60">
                @foreach($resolved as $req)
                    <div class="flex items-center justify-between gap-3 px-5 py-2.5 text-xs">
                        <span class="font-bold text-slate-700 dark:text-slate-200">{{ optional($req->employee)->full_name ?? 'Employee' }} · {{ $req->tool_name }}</span>
                        <span class="font-mono font-bold text-slate-500">{{ $req->code_provided }}</span>
                        <span class="text-slate-400">{{ optional($req->code_sent_at)->diffForHumans() }} · by {{ optional($req->responder)->full_name ?? 'HR' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Declined (collapsible) --}}
    @if(($rejected ?? collect())->count())
        <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                <span class="inline-flex items-center gap-1.5"><i data-lucide="ban" class="h-4 w-4 text-rose-500"></i> Recently declined ({{ $rejected->count() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" x-cloak class="divide-y divide-slate-100 dark:divide-slate-700/60 border-t border-slate-100 dark:border-slate-700/60">
                @foreach($rejected as $req)
                    <div class="px-5 py-2.5 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ optional($req->employee)->full_name ?? 'Employee' }} · {{ $req->tool_name }}</span>
                            <span class="text-slate-400">{{ $req->updated_at->diffForHumans() }} · by {{ optional($req->responder)->full_name ?? 'HR' }}</span>
                        </div>
                        @if($req->rejection_reason)<p class="text-slate-500 dark:text-slate-400 mt-0.5 italic">Reason: {{ $req->rejection_reason }}</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<script>
    function codeCard(id) {
        return {
            code: '', note: '', reason: '', sending: false, rejecting: false,
            showReject: false, done: false, rejected: false, doneMsg: '', error: '',
            send() {
                if (!this.code.trim()) { this.error = 'Enter the code first.'; return; }
                this.sending = true; this.error = '';
                fetch('{{ url('admin/code-requests') }}/' + id + '/send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ code_provided: this.code, code_expires_note: this.note })
                })
                .then(r => r.json())
                .then(d => { this.sending = false; if (d.success) { this.doneMsg = d.message || 'Sent.'; this.done = true; } else { this.error = d.message || 'Could not send.'; } })
                .catch(() => { this.sending = false; this.error = 'Network error. Try again.'; });
            },
            reject() {
                this.rejecting = true; this.error = '';
                fetch('{{ url('admin/code-requests') }}/' + id + '/reject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ rejection_reason: this.reason })
                })
                .then(r => r.json())
                .then(d => { this.rejecting = false; if (d.success) { this.rejected = true; this.doneMsg = d.message || 'Declined.'; this.done = true; } else { this.error = d.message || 'Could not decline.'; } })
                .catch(() => { this.rejecting = false; this.error = 'Network error. Try again.'; });
            }
        }
    }
</script>

{{-- Live queue: new code requests pop in at the top without a refresh. --}}
<script>
(function () {
    const POLL_URL = '{{ route('code-requests.pending-json') }}';
    const list = document.getElementById('pending-list');
    if (!list) return;

    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    function cardHtml(r) {
        const msg = r.message
            ? `<p class="text-xs text-slate-500 dark:text-slate-400 mt-1 italic">"${esc(r.message)}"</p>`
            : '';
        return `
        <div id="code-req-${r.id}" data-req-id="${r.id}"
             class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 dark:bg-slate-800 dark:border-amber-500/30"
             x-data="codeCard(${r.id})" x-show="!done" x-transition>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-extrabold text-slate-900 dark:text-white">
                        ${esc(r.employee)} needs a <span class="text-brand-600">${esc(r.tool)}</span> code
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Requested ${esc(r.ago)} · ${esc(r.request_number)}</p>
                    ${msg}
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 shrink-0">🆕 New</span>
            </div>
            <div class="mt-4 flex flex-col sm:flex-row gap-2">
                <input type="text" x-model="code" placeholder="Paste the code here" class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-mono font-bold dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <input type="text" x-model="note" placeholder="Note (e.g. Valid 10 min)" class="sm:w-44 rounded-xl border border-slate-300 px-3 py-2.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <button type="button" @click="send()" :disabled="sending || rejecting"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50">
                    <i data-lucide="send" class="h-4 w-4"></i><span x-text="sending ? 'Sending…' : 'Send'"></span>
                </button>
                <button type="button" @click="showReject = !showReject" :disabled="sending || rejecting"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100 disabled:opacity-50 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400">
                    <i data-lucide="x" class="h-4 w-4"></i> Decline
                </button>
            </div>
            <div x-show="showReject" x-cloak x-transition class="mt-2 flex flex-col sm:flex-row gap-2">
                <input type="text" x-model="reason" placeholder="Reason (optional — shown to the employee)"
                       class="flex-1 rounded-xl border border-rose-200 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                <button type="button" @click="reject()" :disabled="rejecting"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50">
                    <i data-lucide="ban" class="h-4 w-4"></i><span x-text="rejecting ? 'Declining…' : 'Confirm decline'"></span>
                </button>
            </div>
            <p x-show="error" x-cloak class="text-xs text-rose-600 mt-2" x-text="error"></p>
            <div x-show="done" x-cloak class="text-sm font-bold mt-2" :class="rejected ? 'text-rose-700' : 'text-emerald-700'" x-text="(rejected ? '🚫 ' : '✅ ') + doneMsg"></div>
        </div>`;
    }

    function emptyStateHtml() {
        return `
        <div id="pending-empty" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="check-check" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">All caught up</p>
            <p class="text-xs text-slate-400 mt-1">No employees waiting for a code right now.</p>
        </div>`;
    }

    function highlight(el) {
        el.classList.add('ring-2', 'ring-emerald-400', 'ring-offset-2', 'ring-offset-slate-50', 'dark:ring-offset-slate-900');
        setTimeout(() => el.classList.remove('ring-2', 'ring-emerald-400', 'ring-offset-2', 'ring-offset-slate-50', 'dark:ring-offset-slate-900'), 4000);
    }

    let refreshing = false;
    async function refresh() {
        if (refreshing || document.hidden) return;
        refreshing = true;
        try {
            const res = await fetch(POLL_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            const pending = Array.isArray(data.pending) ? data.pending : [];
            const serverIds = new Set(pending.map(r => Number(r.id)));

            // Drop cards that were handled (by anyone) and are no longer pending.
            list.querySelectorAll('[data-req-id]').forEach(el => {
                if (!serverIds.has(Number(el.getAttribute('data-req-id')))) el.remove();
            });

            // Insert genuinely new requests — newest first, so they land at the very top.
            let inserted = false;
            for (let i = pending.length - 1; i >= 0; i--) {
                const r = pending[i];
                if (document.getElementById('code-req-' + r.id)) continue;
                const empty = document.getElementById('pending-empty');
                if (empty) empty.remove();
                const tmp = document.createElement('div');
                tmp.innerHTML = cardHtml(r).trim();
                const card = tmp.firstElementChild;
                list.insertBefore(card, list.firstChild); // Alpine v3 auto-initialises added nodes.
                highlight(card);
                inserted = true;
            }

            // Restore the empty state when the queue drains.
            if (!list.querySelector('[data-req-id]') && !document.getElementById('pending-empty')) {
                list.insertAdjacentHTML('beforeend', emptyStateHtml());
            }

            if (inserted && window.lucide) lucide.createIcons();
        } catch (e) {
            /* transient network error — try again next tick */
        } finally {
            refreshing = false;
        }
    }

    setInterval(refresh, 12000);
    setTimeout(refresh, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
</script>
@endsection
