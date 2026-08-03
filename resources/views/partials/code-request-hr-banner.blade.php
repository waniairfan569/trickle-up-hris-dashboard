@php
    $pendingCodeCount = \App\Models\CodeRequest::where('status', 'pending')->count();
    // Only super admins / HR admins may hit the pending-json endpoint, so only they poll it live.
    $canPollCodes = auth()->check() && (auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('hr_admin'));
@endphp
<a x-data="codeRequestHrBanner({{ $pendingCodeCount }}, {{ $canPollCodes ? 'true' : 'false' }})" x-init="init()"
   x-show="count > 0" x-cloak x-transition
   :href="pendingUrl"
   class="flex items-center justify-between gap-3 rounded-xl bg-amber-50 border border-amber-200 px-5 py-3.5 dark:bg-amber-500/10 dark:border-amber-500/20 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition"
   :class="bump ? 'ring-2 ring-amber-400 ring-offset-2 ring-offset-slate-50 dark:ring-offset-slate-900' : ''">
    <div class="flex items-center gap-3">
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/20"><i data-lucide="zap" class="h-5 w-5"></i></span>
        <div>
            <p class="text-sm font-extrabold text-amber-900 dark:text-amber-200">⚡ <span x-text="count"></span> <span x-text="count === 1 ? 'employee' : 'employees'"></span> waiting for a login code</p>
            <p class="text-[11px] text-amber-700 dark:text-amber-400">They’re locked out of a tool — respond fast.</p>
        </div>
    </div>
    <span class="inline-flex items-center gap-1 text-sm font-bold text-amber-800 dark:text-amber-300">Respond now <i data-lucide="arrow-right" class="h-4 w-4"></i></span>
</a>

<script>
    function codeRequestHrBanner(initial, canPoll) {
        return {
            count: initial || 0,
            bump: false,
            canPoll: !!canPoll,
            pendingUrl: '{{ route('code-requests.pending') }}',
            init() {
                this.$nextTick(() => window.lucide && lucide.createIcons());
                if (!this.canPoll) return;
                // Live: an incoming code request shows up here without a page refresh.
                setInterval(() => this.refresh(), 12000);
                document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refresh(); });
            },
            refresh() {
                if (document.hidden) return;
                fetch('{{ route('code-requests.pending-json') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(r => r.ok ? r.json() : null)
                    .then(d => {
                        if (!d) return;
                        const n = Array.isArray(d.pending) ? d.pending.length : 0;
                        if (n > this.count) { this.bump = true; setTimeout(() => { this.bump = false; }, 4000); }
                        this.count = n;
                        this.$nextTick(() => window.lucide && lucide.createIcons());
                    })
                    .catch(() => {});
            }
        }
    }
</script>
