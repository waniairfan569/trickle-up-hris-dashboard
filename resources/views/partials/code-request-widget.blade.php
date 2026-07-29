@php
    $myCodeRequests = \App\Models\CodeRequest::where('employee_id', auth()->id())->latest()->take(5)->get();
    $freshCode = $myCodeRequests->first(fn($r) => $r->status === 'code_sent' && $r->code_sent_at && $r->code_sent_at->gt(now()->subMinutes(60)));
    $codeTools = [
        'Higgsfield', 'Envato', 'Freepik', 'Semrush', 'Canva', 'KIE.AI',
        'Adobe Creative Cloud ( Big Byte Store)', 'Figma', 'OpenRouter', 'Capcut',
        'Claude.Ai', 'Claude ( Project)', 'Claude Ember', 'Claude (SEO account)',
        'Claude Dusk', 'Claude Cobalt', 'Claude Crimson', 'FireCrawl', 'Other',
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 dark:bg-slate-800 dark:border-slate-700"
     x-data="codeRequestWidget()">

    {{-- Green banner when a code just arrived --}}
    @if($freshCode)
        <a href="{{ route('code-requests.my') }}" class="block mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 dark:bg-emerald-500/10 dark:border-emerald-500/20">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-extrabold text-emerald-800 dark:text-emerald-300">🔑 Your {{ $freshCode->tool_name }} code is here!</p>
                    <p class="text-lg font-mono font-extrabold tracking-widest text-emerald-900 dark:text-emerald-200 mt-1">{{ $freshCode->code_provided }}</p>
                    @if($freshCode->code_expires_note)<p class="text-[11px] font-bold text-rose-600 mt-0.5">⏱ {{ $freshCode->code_expires_note }}</p>@endif
                </div>
                <i data-lucide="chevron-right" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            </div>
        </a>
    @endif

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
        <p class="text-xs text-emerald-700 dark:text-emerald-400 mt-1">HR will share the code shortly. Watch your notifications 🔔</p>
        <button type="button" @click="sent=false" class="mt-2 text-xs font-bold text-emerald-700 underline">Send another</button>
    </div>

    {{-- Full history lives on the "View all" page. --}}
    @if($myCodeRequests->count())
        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('code-requests.my') }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-brand-600 hover:text-brand-700">
                <i data-lucide="history" class="h-3.5 w-3.5"></i> View my request history →
            </a>
        </div>
    @endif
</div>

<script>
    function codeRequestWidget() {
        return {
            tool: '', otherTool: '', message: '', sending: false, sent: false, error: '',
            get canSubmit() {
                const name = this.tool === 'Other' ? this.otherTool.trim() : this.tool;
                return !!name && this.message.trim().length > 0;
            },
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
                .then(d => { this.sending = false; if (d.success) { this.sent = true; this.message = ''; } else { this.error = d.message || 'Could not send. Try again.'; } })
                .catch(() => { this.sending = false; this.error = 'Network error. Try again.'; });
            }
        }
    }
</script>
