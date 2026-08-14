{{-- Top bar + auto-popup for announcements the current user hasn't read yet.
     Included globally in the layout. Once acknowledged, they never auto-pop again. --}}
@php
    $__annUser = auth()->user();
    $__unreadAnn = $__annUser
        ? \App\Models\Announcement::unreadFor($__annUser)->with('creator')->orderByDesc('is_pinned')->latest()->take(15)->get()
        : collect();
@endphp

@if($__unreadAnn->isNotEmpty())
    @php
        $__annItems = $__unreadAnn->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'body' => (string) $a->bodyHtml(),
            'meta' => (optional($a->creator)->full_name ?? 'Admin') . ' · ' . $a->created_at->diffForHumans(),
            'pinned' => (bool) $a->is_pinned,
        ])->values();
    @endphp
    <style>[x-cloak]{display:none!important}</style>
    <div x-data="announcementAlert(@js($__annItems), {{ request()->routeIs('dashboard') ? 'true' : 'false' }})" x-cloak class="mb-6">
        {{-- Slim banner --}}
        <div x-show="items.length" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 dark:border-brand-500/30 dark:bg-brand-500/10">
            <div class="flex items-center gap-2.5 text-sm font-bold text-slate-800 dark:text-brand-100">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500/20 text-brand-600 dark:text-brand-400"><i data-lucide="megaphone" class="h-4 w-4"></i></span>
                <span>You have <span x-text="items.length"></span> new announcement<span x-show="items.length !== 1">s</span></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="show()" class="rounded-lg bg-brand-600 px-3.5 py-1.5 text-xs font-bold text-slate-900 hover:bg-brand-700">Read now</button>
                <button type="button" @click="acknowledge()" title="Mark all as read" class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white"><i data-lucide="check-check" class="h-4 w-4"></i></button>
            </div>
        </div>

        {{-- Auto-popup (shows on arrival while there are unread ones) --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="dismiss()"></div>
            <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl dark:bg-slate-800 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                    <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="megaphone" class="h-5 w-5 text-brand-500 shrink-0"></i>
                        <span>New announcement<span x-show="items.length !== 1">s</span> (<span x-text="items.length"></span>)</span>
                    </h3>
                    <button type="button" @click="dismiss()" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <div class="overflow-y-auto px-6 py-4 space-y-4">
                    <template x-for="a in items" :key="a.id">
                        <div class="rounded-xl border p-4" :class="a.pinned ? 'border-brand-200 bg-brand-50/50 dark:border-brand-500/20 dark:bg-brand-500/5' : 'border-slate-200/70 dark:border-slate-700/60'">
                            <div class="flex items-start gap-2">
                                <template x-if="a.pinned"><i data-lucide="pin" class="h-3.5 w-3.5 text-brand-500 mt-1 shrink-0"></i></template>
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white" x-text="a.title"></h4>
                            </div>
                            <div class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mt-1.5" x-html="a.body"></div>
                            <p class="text-[11px] text-slate-400 mt-2" x-text="a.meta"></p>
                        </div>
                    </template>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2">
                    <button type="button" @click="dismiss()" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300">Later</button>
                    <button type="button" @click="acknowledge()" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Got it — mark all read</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function announcementAlert(items, autoOpen) {
            return {
                items: items || [],
                autoOpen: !!autoOpen,
                open: false,
                csrf: document.querySelector('meta[name=csrf-token]').content,
                init() {
                    // On the dashboard, pop every visit while there are unread ones
                    // (until the user reads them). Elsewhere, only the bar shows.
                    if (this.autoOpen && this.items.length) {
                        this.open = true;
                        this.$nextTick(() => window.lucide && lucide.createIcons());
                    }
                },
                show() { this.open = true; this.$nextTick(() => window.lucide && lucide.createIcons()); },
                dismiss() { this.open = false; },   // close for now — stays in the banner
                async acknowledge() {
                    try {
                        await fetch('{{ route('announcements.read-all') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        });
                    } catch (e) { /* offline — will re-pop next load */ }
                    this.items = [];
                    this.open = false;
                },
            };
        }
    </script>
@endif
