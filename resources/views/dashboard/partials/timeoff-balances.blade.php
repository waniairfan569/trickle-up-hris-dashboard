<!-- Time-off Balances Widget -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
    <div class="flex items-center gap-2 mb-6">
        <i data-lucide="calendar-check" class="h-5 w-5 text-slate-400"></i>
        <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your time-off balances</h2>
    </div>

    <div x-data="balanceSlider({{ $timeOffBalances->count() }})">
        <div x-ref="slider" @scroll.debounce.50ms="onScroll()" class="flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-1">
            @forelse($timeOffBalances as $index => $b)
                @php
                    $total = $b->opening_balance + $b->accrued + $b->adjusted + $b->carried_over;
                    $remaining = max(0, $total - $b->used - $b->pending);
                    $policyName = optional($b->policy)->name ?? 'Unpaid Leave';
                    if (stripos($policyName, 'Annual') !== false) { $displayName = 'Planned Leaves'; }
                    elseif (stripos($policyName, 'Casual') !== false) { $displayName = 'Unplanned'; }
                    else { $displayName = $policyName; }
                    $color = ['bg-cyan-400', 'bg-amber-400', 'bg-rose-400', 'bg-emerald-400', 'bg-indigo-400'][$index % 5];
                @endphp
                <div class="snap-start flex-shrink-0 w-44 border border-slate-100 rounded-xl p-4 flex flex-col justify-between h-28 relative overflow-hidden dark:border-slate-700">
                    <div class="absolute left-0 top-0 bottom-0 w-1 {{ $color }}"></div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 ml-2 truncate" title="{{ $policyName }}">{{ $displayName }}</h3>
                    <div class="ml-2">
                        <span class="text-2xl font-bold text-slate-800 dark:text-white">{{ floatval($remaining) }}</span>
                        <span class="text-[11px] text-slate-500 font-medium">days available</span>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No time-off balances found.</div>
            @endforelse
        </div>

        <div x-show="count > 1" class="flex justify-center gap-1.5 mt-4 mb-1">
            <template x-for="i in count" :key="i">
                <button type="button" @click="goTo(i - 1)" :aria-label="'Go to slide ' + i"
                        :class="active === (i - 1) ? 'bg-brand-500 w-4' : 'bg-slate-300 dark:bg-slate-600 w-1.5 hover:bg-slate-400'"
                        class="h-1.5 rounded-full transition-all duration-200"></button>
            </template>
        </div>
    </div>

    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    <script>
        function balanceSlider(count) {
            return {
                count: count,
                active: 0,
                onScroll() {
                    const s = this.$refs.slider;
                    if (!s.children.length) return;
                    const base = s.children[0].offsetLeft;
                    let best = 0, bestDist = Infinity;
                    Array.from(s.children).forEach((c, i) => {
                        const dist = Math.abs((c.offsetLeft - base) - s.scrollLeft);
                        if (dist < bestDist) { bestDist = dist; best = i; }
                    });
                    this.active = best;
                },
                goTo(i) {
                    const s = this.$refs.slider;
                    const c = s.children[i];
                    if (c) s.scrollTo({ left: c.offsetLeft - s.children[0].offsetLeft, behavior: 'smooth' });
                },
            };
        }
    </script>

    <div class="flex gap-2 mt-5">
        <a href="{{ route('time-off.create') }}" class="flex-1 bg-brand-600 hover:bg-brand-700 text-slate-900 text-sm font-bold py-3 px-4 rounded-lg flex items-center justify-center gap-2 transition">
            <i data-lucide="calendar-plus" class="h-4 w-4"></i> Request time off
        </a>
    </div>
</div>
