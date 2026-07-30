@php
    // Self-providing: callers can pass $catalog, otherwise build it here.
    $catalog = $catalog ?? app(\App\Services\DocumentTokenService::class)->availableTokens();
@endphp
<div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700" x-data="{ open: false, copied: '' }">
    <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 text-left">
        <div class="flex items-center gap-2.5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i data-lucide="braces" class="h-5 w-5"></i></span>
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-white">Available tokens</h2>
                <p class="text-xs text-slate-400">Put these in your Word/PDF where employee data should appear — they auto-fill from each employee's profile.</p>
            </div>
        </div>
        <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition-transform" :class="open && 'rotate-180'"></i>
    </button>

    <div x-show="open" x-cloak class="mt-4 space-y-4">
        @foreach($catalog as $group => $items)
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">{{ $group }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($items as $it)
                        <button type="button"
                                @click="navigator.clipboard && navigator.clipboard.writeText(@js($it['token'])); copied = @js($it['token']); setTimeout(() => { if (copied === @js($it['token'])) copied = '' }, 1200)"
                                :title="'Copy ' + @js($it['token'])"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs hover:border-brand-300 hover:bg-brand-50/50 dark:border-slate-600 dark:bg-slate-900 dark:hover:bg-slate-700 transition">
                            <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $it['label'] }}</span>
                            <span class="font-mono text-brand-700 dark:text-brand-300">{{ $it['token'] }}</span>
                            <i data-lucide="copy" class="h-3 w-3 text-slate-400" x-show="copied !== @js($it['token'])"></i>
                            <i data-lucide="check" class="h-3 w-3 text-emerald-500" x-show="copied === @js($it['token'])" x-cloak></i>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="rounded-xl bg-amber-50/60 border border-amber-100 px-3 py-2.5 dark:bg-amber-500/5 dark:border-amber-500/20">
            <p class="text-[11px] text-amber-800 dark:text-amber-300">
                <span class="font-bold">Want the employee to fill something in themselves</span> (e.g. a loan amount)? Just write a bracket name that isn't in this list — like <span class="font-mono">[Loan Amount]</span> — and the employee is asked for it when they sign or acknowledge.
            </p>
        </div>
    </div>
</div>
