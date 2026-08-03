@php
    $__myCodeRequests = \App\Models\CodeRequest::where('employee_id', auth()->id())->with('responder')->latest()->take(8)->get();
    $__initialItems = $__myCodeRequests->map(fn($r) => [
        'id' => $r->id,
        'tool' => $r->tool_name,
        'message' => $r->message,
        'status' => $r->status,
        'request_number' => $r->request_number,
        'ago' => optional($r->created_at)->diffForHumans(),
        'code' => $r->code_provided,
        'code_note' => $r->code_expires_note,
        'sent_ago' => optional($r->code_sent_at)->diffForHumans(),
        'responder' => optional($r->responder)->full_name ?? 'HR',
        'rejection_reason' => $r->rejection_reason,
        'fresh' => $r->status === 'code_sent' && $r->code_sent_at && $r->code_sent_at->gt(now()->subMinutes(60)),
    ])->values();
    $codeTools = [
        'Higgsfield', 'Envato', 'Freepik', 'Semrush', 'Canva', 'KIE.AI',
        'Adobe Creative Cloud ( Big Byte Store)', 'Figma', 'OpenRouter', 'Capcut',
        'Claude.Ai', 'Claude ( Project)', 'Claude Ember', 'Claude (SEO account)',
        'Claude Dusk', 'Claude Cobalt', 'Claude Crimson', 'FireCrawl', 'Other',
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 dark:bg-slate-800 dark:border-slate-700"
     x-data="codeRequestWidget(@js($__initialItems))" x-init="init()">

    {{-- Green banner when a code just arrived (updates live — no refresh needed) --}}
    <template x-if="freshCode">
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 dark:bg-emerald-500/10 dark:border-emerald-500/20 transition"
             :class="justArrived ? 'ring-2 ring-emerald-400 ring-offset-2 ring-offset-white dark:ring-offset-slate-800' : ''">
            <div class="flex items-start justify-between gap-3">
                <a :href="'{{ route('code-requests.my') }}'" class="block min-w-0">
                    <p class="text-sm font-extrabold text-emerald-800 dark:text-emerald-300">🔑 Your <span x-text="freshCode.tool"></span> code is here!</p>
                    <p class="text-lg font-mono font-extrabold tracking-widest text-emerald-900 dark:text-emerald-200 mt-1 break-all" x-text="freshCode.code"></p>
                    <p x-show="freshCode.code_note" x-cloak class="text-[11px] font-bold text-rose-600 mt-0.5">⏱ <span x-text="freshCode.code_note"></span></p>
                </a>
                <button type="button" @click="dismiss(freshCode.id)" class="shrink-0 text-emerald-600/70 hover:text-emerald-700" title="Dismiss">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
        </div>
    </template>

    <div class="flex items-center gap-2 mb-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="key-round" class="h-5 w-5"></i></span>
        <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Need a login code?</h3>
            <p class="text-[11px] text-slate-400">Locked out of a company tool? Ask HR to share the code.</p>
        </div>
    </div>

    {{-- Form (hidden after success) --}}
    <div x-show="!sent">
        <div class="flex flex-col sm:flex-row gap-2">
            <select x-model="tool" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <option value="" disabled>Select a tool…</option>
                @foreach($codeTools as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
            </select>
            <input x-show="tool === 'Other'" x-cloak type="text" x-model="otherTool" placeholder="Tool name" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
        <input type="text" x-model="message" maxlength="255" placeholder="Reason for this request (required)" class="w-full mt-2 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        <button type="button" @click="submit()" :disabled="sending || !canSubmit"
                class="w-full mt-3 inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
            <i data-lucide="send" class="h-4 w-4"></i>
            <span x-text="sending ? 'Sending…' : 'Request Code from HR'"></span>
        </button>
        <p x-show="error" x-cloak class="text-xs text-rose-600 mt-2" x-text="error"></p>
    </div>

    {{-- Success --}}
    <div x-show="sent" x-cloak class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-center dark:bg-emerald-500/10 dark:border-emerald-500/20">
        <p class="text-sm font-bold text-emerald-800 dark:text-emerald-300">✅ Request sent!</p>
        <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">HR will share the code shortly. It’ll appear here automatically 🔔</p>
        <button type="button" @click="sent=false" class="mt-2 text-xs font-bold text-emerald-700 underline">Send another</button>
    </div>

    {{-- Pending requests — cancel anything you no longer need (updates live) --}}
    <template x-if="pendingItems.length">
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 space-y-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Waiting on HR</p>
            <template x-for="r in pendingItems" :key="r.id">
                <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-900/40">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200 truncate">
                            <span x-text="r.tool"></span>
                            <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">⏳ Pending</span>
                        </p>
                        <p class="text-[10px] text-slate-400" x-text="r.ago"></p>
                    </div>
                    <button type="button" @click="cancel(r.id)" :disabled="r.cancelling"
                            class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 disabled:opacity-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300">
                        <i data-lucide="x" class="h-3 w-3"></i><span x-text="r.cancelling ? 'Cancelling…' : 'Cancel'"></span>
                    </button>
                </div>
            </template>
        </div>
    </template>

    {{-- Full history lives on the "View all" page. --}}
    <template x-if="items.length">
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('code-requests.my') }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-600 hover:text-brand-700">
                <i data-lucide="history" class="h-3.5 w-3.5"></i> View my request history →
            </a>
        </div>
    </template>
</div>

<script>
    function codeRequestWidget(initial) {
        return {
            tool: '', otherTool: '', message: '', sending: false, sent: false, error: '',
            items: (initial || []).map(r => ({ ...r, cancelling: false })),
            dismissed: {}, justArrived: false,

            get canSubmit() {
                const name = this.tool === 'Other' ? this.otherTool.trim() : this.tool;
                return !!name && this.message.trim().length > 0;
            },
            get pendingItems() { return this.items.filter(r => r.status === 'pending'); },
            get freshCode() {
                return this.items.find(r => r.status === 'code_sent' && r.fresh && !this.dismissed[r.id]) || null;
            },

            init() {
                this.$nextTick(() => window.lucide && lucide.createIcons());
                setInterval(() => this.refresh(), 15000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
            },

            dismiss(id) { this.dismissed[id] = true; },

            submit() {
                const name = this.tool === 'Other' ? this.otherTool.trim() : this.tool;
                if (!name) { this.error = 'Please choose or type a tool name.'; return; }
                if (!this.message.trim()) { this.error = 'Please add a reason for this request.'; return; }
                this.sending = true; this.error = '';
                fetch('{{ route('code-requests.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify({ tool_name: name, message: this.message })
                })
                .then(r => r.json())
                .then(d => {
                    this.sending = false;
                    if (d.success) { this.sent = true; this.message = ''; this.tool = ''; this.otherTool = ''; this.refresh(); }
                    else { this.error = d.message || 'Could not send. Try again.'; }
                })
                .catch(() => { this.sending = false; this.error = 'Network error. Try again.'; });
            },

            cancel(id) {
                const row = this.items.find(r => r.id === id);
                if (!row || row.cancelling) return;
                row.cancelling = true;
                fetch('{{ url('code-requests') }}/' + id + '/cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { this.items = this.items.filter(r => r.id !== id); }
                    else { row.cancelling = false; this.error = d.message || 'Could not cancel.'; }
                })
                .catch(() => { row.cancelling = false; this.error = 'Network error. Try again.'; });
            },

            refresh() {
                if (document.hidden) return;
                fetch('{{ route('code-requests.my-json') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => {
                        const fresh = Array.isArray(d.items) ? d.items : [];
                        // Detect a code that just arrived (was pending, now sent) to flash the banner.
                        const wasPending = new Set(this.items.filter(r => r.status === 'pending').map(r => r.id));
                        const newlySent = fresh.some(r => r.status === 'code_sent' && wasPending.has(r.id));
                        // Preserve any in-flight "cancelling" flag.
                        const cancelling = new Set(this.items.filter(r => r.cancelling).map(r => r.id));
                        this.items = fresh.map(r => ({ ...r, cancelling: cancelling.has(r.id) }));
                        if (newlySent) {
                            this.justArrived = true;
                            setTimeout(() => { this.justArrived = false; }, 4000);
                        }
                        this.$nextTick(() => window.lucide && lucide.createIcons());
                    })
                    .catch(() => {});
            }
        }
    }
</script>
