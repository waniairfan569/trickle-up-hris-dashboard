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
                    <input type="text" x-model="reason" maxlength="255" placeholder="Reason (required — shown to the employee)"
                           class="flex-1 rounded-xl border border-rose-200 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                    <button type="button" @click="reject()" :disabled="rejecting || !reason.trim()"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed">
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

    {{-- History search (by employee or tool) --}}
    @if($resolved->total() || $rejected->total() || $search !== '')
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <i data-lucide="search" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search history by employee or tool…" class="w-full rounded-xl border border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <button type="submit" class="btn-dark btn-sm">Search</button>
            @if($search !== '')<a href="{{ route('code-requests.pending') }}" class="btn-outline btn-sm">Clear</a>@endif
        </form>
    @endif

    {{-- Recently sent — paginated; codes are hidden until an admin reveals one --}}
    @if($resolved->total())
        <div x-data="{ open: {{ request()->has('sent') || $search !== '' ? 'true' : 'false' }} }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                <span>Recently sent ({{ $resolved->total() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" x-cloak class="border-t border-slate-100 dark:border-slate-700/60">
                @php $sentToggle = $sort === 'oldest' ? 'newest' : 'oldest'; @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                                <th class="px-5 py-2.5 font-bold uppercase tracking-wide">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'employee', 'sent' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-600 dark:hover:text-slate-200 {{ $sort === 'employee' ? 'text-slate-600 dark:text-slate-200' : '' }}">Employee @if($sort === 'employee')<i data-lucide="chevron-down" class="h-3 w-3"></i>@endif</a>
                                </th>
                                <th class="px-3 py-2.5 font-bold uppercase tracking-wide">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'tool', 'sent' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-600 dark:hover:text-slate-200 {{ $sort === 'tool' ? 'text-slate-600 dark:text-slate-200' : '' }}">Tool @if($sort === 'tool')<i data-lucide="chevron-down" class="h-3 w-3"></i>@endif</a>
                                </th>
                                <th class="px-3 py-2.5 font-bold uppercase tracking-wide">Code</th>
                                <th class="px-3 py-2.5 font-bold uppercase tracking-wide">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => $sentToggle, 'sent' => 1]) }}" class="inline-flex items-center gap-1 hover:text-slate-600 dark:hover:text-slate-200 {{ in_array($sort, ['newest', 'oldest'], true) ? 'text-slate-600 dark:text-slate-200' : '' }}">Sent <i data-lucide="chevron-{{ $sort === 'oldest' ? 'up' : 'down' }}" class="h-3 w-3"></i></a>
                                </th>
                                <th class="px-5 py-2.5 font-bold uppercase tracking-wide">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @foreach($resolved as $req)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                                    <td class="px-5 py-2.5 font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ optional($req->employee)->full_name ?? 'Employee' }}</td>
                                    <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $req->tool_name }}</td>
                                    <td class="px-3 py-2.5">
                                        <div x-data="revealCode({{ $req->id }})" class="flex items-center gap-1.5">
                                            @if($req->hasCode())
                                                @php $vt = $req->valueType(); @endphp
                                                @if($vt)<span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300 shrink-0">{{ $vt }}</span>@endif
                                                <span class="font-mono font-bold text-slate-500 dark:text-slate-300 truncate max-w-[160px]" x-text="display"></span>
                                                <button type="button" @click="toggle()" class="text-slate-400 hover:text-slate-600 shrink-0" :title="shown ? 'Hide' : 'Reveal'"><i data-lucide="eye" class="h-3.5 w-3.5" x-show="!shown"></i><i data-lucide="eye-off" class="h-3.5 w-3.5" x-show="shown" x-cloak></i></button>
                                                <button type="button" @click="copy()" class="text-slate-400 hover:text-brand-600 shrink-0" :title="copied ? 'Copied!' : 'Copy'"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                                                <button type="button" @click="resend()" class="text-slate-400 hover:text-emerald-600 shrink-0" title="Resend to employee"><i data-lucide="send" class="h-3.5 w-3.5"></i></button>
                                                <span x-show="resendMsg" x-cloak x-text="resendMsg" class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 shrink-0"></span>
                                            @else
                                                <span class="text-slate-300 dark:text-slate-600 italic">cleared</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap" title="{{ optional($req->code_sent_at)->format('l, d M Y · H:i') }}">
                                        {{ optional($req->code_sent_at)->format('d M Y') }}
                                        <span class="block text-[10px] text-slate-400">{{ optional($req->code_sent_at)->format('g:i A') }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ optional($req->responder)->full_name ?? 'HR' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($resolved->hasPages())
                    <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700/60">{{ $resolved->links() }}</div>
                @endif
            </div>
        </div>
    @endif

    {{-- Recently declined — paginated --}}
    @if($rejected->total())
        <div x-data="{ open: {{ request()->has('declined') ? 'true' : 'false' }} }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button type="button" @click="open = !open" class="w-full flex items-center justify-between px-5 py-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                <span class="inline-flex items-center gap-1.5"><i data-lucide="ban" class="h-4 w-4 text-rose-500"></i> Recently declined ({{ $rejected->total() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" x-cloak class="border-t border-slate-100 dark:border-slate-700/60">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                                <th class="px-5 py-2.5 font-bold uppercase tracking-wide">Employee</th>
                                <th class="px-3 py-2.5 font-bold uppercase tracking-wide">Tool</th>
                                <th class="px-3 py-2.5 font-bold uppercase tracking-wide">Reason</th>
                                <th class="px-3 py-2.5 font-bold uppercase tracking-wide">Declined</th>
                                <th class="px-5 py-2.5 font-bold uppercase tracking-wide">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @foreach($rejected as $req)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30" x-data="declineRow({{ $req->id }}, @js($req->rejection_reason ?? ''))">
                                    <td class="px-5 py-2.5 font-bold text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ optional($req->employee)->full_name ?? 'Employee' }}</td>
                                    <td class="px-3 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $req->tool_name }}</td>
                                    <td class="px-3 py-2.5 min-w-[180px]">
                                        <div x-show="!editing" class="flex items-center gap-1.5">
                                            <span x-text="reason || 'No reason recorded'" :class="reason ? 'text-slate-500 dark:text-slate-400 italic' : 'text-slate-300 dark:text-slate-600 italic'"></span>
                                            <button type="button" @click="editing = true" class="font-bold text-brand-600 hover:text-brand-700 shrink-0">Edit</button>
                                        </div>
                                        <div x-show="editing" x-cloak class="flex items-center gap-1.5">
                                            <input type="text" x-model="draft" maxlength="255" placeholder="Reason…" class="w-40 rounded-lg border border-slate-300 py-1 px-2 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            <button type="button" @click="save()" :disabled="saving || !draft.trim()" class="btn-brand btn-sm">Save</button>
                                            <button type="button" @click="editing = false; draft = reason" class="btn-outline btn-sm">Cancel</button>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap" title="{{ $req->updated_at->format('l, d M Y · H:i') }}">
                                        {{ $req->updated_at->format('d M Y') }}
                                        <span class="block text-[10px] text-slate-400">{{ $req->updated_at->format('g:i A') }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ optional($req->responder)->full_name ?? 'HR' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($rejected->hasPages())
                    <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700/60">{{ $rejected->links() }}</div>
                @endif
            </div>
        </div>
    @endif
</div>

<script>
    // Reveals a stored code on demand (fetched from an admin-only endpoint, so
    // codes are never present in the page HTML on load).
    function revealCode(id) {
        return {
            id, shown: false, value: null, loading: false, copied: false,
            get display() { return this.loading ? '…' : (this.shown ? (this.value || '—') : '••••••'); },
            async fetchVal() {
                if (this.value !== null) return;
                this.loading = true;
                try {
                    const r = await fetch(`{{ url('admin/code-requests') }}/${this.id}/reveal`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const d = await r.json();
                    this.value = d.value ?? '';
                } catch (e) { this.value = ''; }
                this.loading = false;
            },
            async toggle() { if (!this.shown) await this.fetchVal(); this.shown = !this.shown; },
            async copy() {
                await this.fetchVal();
                try { await navigator.clipboard.writeText(this.value || ''); this.copied = true; setTimeout(() => this.copied = false, 1200); } catch (e) {}
            },
            resendMsg: '',
            async resend() {
                try {
                    const r = await fetch(`{{ url('admin/code-requests') }}/${this.id}/resend`, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, credentials: 'same-origin' });
                    const d = await r.json();
                    this.resendMsg = d.success ? 'Resent ✓' : (d.message || 'Failed');
                } catch (e) { this.resendMsg = 'Network error'; }
                setTimeout(() => this.resendMsg = '', 2800);
            },
        };
    }

    // Inline editing of a decline reason.
    function declineRow(id, reason) {
        return {
            id, reason: reason || '', draft: reason || '', editing: false, saving: false,
            async save() {
                if (!this.draft.trim()) return;
                this.saving = true;
                try {
                    const r = await fetch(`{{ url('admin/code-requests') }}/${this.id}/reason`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, credentials: 'same-origin', body: JSON.stringify({ rejection_reason: this.draft.trim() }) });
                    const d = await r.json();
                    if (d.success) { this.reason = d.reason; this.editing = false; }
                } catch (e) {}
                this.saving = false;
            },
        };
    }

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
                <input type="text" x-model="reason" maxlength="255" placeholder="Reason (required — shown to the employee)"
                       class="flex-1 rounded-xl border border-rose-200 px-3.5 py-2.5 text-xs dark:bg-slate-900 dark:border-rose-500/30 dark:text-white">
                <button type="button" @click="reject()" :disabled="rejecting || !reason.trim()"
                        class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed">
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
