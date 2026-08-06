<!-- Time-off Balances Widget -->
<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 flex flex-col dark:bg-slate-800 dark:border-slate-700">
    <div class="flex items-center gap-3 mb-6">
        <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
            <i data-lucide="calendar-check" class="h-5 w-5"></i>
        </div>
        <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your time-off balances</h2>
    </div>

    <div x-data="balanceSlider({{ $timeOffBalances->count() }})" class="relative">
        <!-- Left Arrow -->
        <button type="button" @click="scrollPrev()" x-show="active > 0" class="absolute -left-2 top-[56px] -translate-y-1/2 z-10 p-1.5 bg-slate-50 dark:bg-slate-700 border border-slate-100 dark:border-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition flex items-center justify-center shadow-sm">
            <i data-lucide="chevron-left" class="h-4 w-4 text-slate-600 dark:text-slate-200"></i>
        </button>

        <div x-ref="slider" @scroll.debounce.50ms="onScroll()" class="flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-1 scroll-smooth">
            @forelse($timeOffBalances as $index => $b)
                @php
                    $total = $b->opening_balance + $b->accrued + $b->adjusted + $b->carried_over;
                    $remaining = max(0, $total - $b->used - $b->pending);
                    $policyName = optional($b->policy)->name ?? 'Unpaid Leave';

                    if (stripos($policyName, 'Annual') !== false) {
                        $displayName = 'Planned Leaves';
                    } elseif (stripos($policyName, 'Casual') !== false) {
                        $displayName = 'Unplanned Leaves';
                    } else {
                        $displayName = $policyName;
                    }

                    $color = ['bg-cyan-300', 'bg-amber-300', 'bg-rose-300', 'bg-emerald-300', 'bg-indigo-300'][$index % 5];
                    $leaveUnit = optional(auth()->user()->company)->leave_unit ?? 'days';
                    $isUnpaid = !$b->policy || !$b->policy->is_paid;
                @endphp
                <div class="snap-start flex-shrink-0 w-[calc(50%-8px)] min-w-[190px] border border-slate-100 rounded-xl p-4 flex flex-col justify-between h-28 relative overflow-hidden dark:border-slate-700 bg-white dark:bg-slate-800">
                    <div class="absolute left-3 top-3 bottom-3 w-1 rounded-full {{ $color }}"></div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200 pl-2 truncate" title="{{ $policyName }}">{{ $displayName }}</h3>
                    <div class="pl-2">
                        <span class="text-2xl font-bold text-slate-800 dark:text-white">
                            @if($isUnpaid)
                                &infin;
                            @else
                                {{ floatval($remaining) }}
                            @endif
                        </span>
                        <span class="text-[11px] text-slate-500 font-medium">{{ $leaveUnit === 'hours' ? 'hours available' : 'days available' }}</span>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">No time-off balances found.</div>
            @endforelse
        </div>

        <!-- Right Arrow -->
        <button type="button" @click="scrollNext()" x-show="active < count - 2" class="absolute -right-2 top-[56px] -translate-y-1/2 z-10 p-1.5 bg-slate-50 dark:bg-slate-700 border border-slate-100 dark:border-slate-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-600 transition flex items-center justify-center shadow-sm">
            <i data-lucide="chevron-right" class="h-4 w-4 text-slate-600 dark:text-slate-200"></i>
        </button>

        <!-- Navigation dots -->
        <div x-show="count > 1" class="flex justify-center gap-1.5 mt-4 mb-1">
            <template x-for="i in count" :key="i">
                <button type="button" @click="goTo(i - 1)" :aria-label="'Go to slide ' + i"
                        :class="active === (i - 1) ? 'bg-slate-800 dark:bg-slate-100 w-4' : 'bg-slate-300 dark:bg-slate-600 w-1.5 hover:bg-slate-400'"
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
                scrollNext() {
                    if (this.active < this.count - 1) {
                        this.goTo(this.active + 1);
                    }
                },
                scrollPrev() {
                    if (this.active > 0) {
                        this.goTo(this.active - 1);
                    }
                }
            };
        }
    </script>

    <div class="flex gap-2 mt-5">
        <a href="{{ route('time-off.create') }}" class="btn-brand btn-block py-3">
            <i data-lucide="calendar-plus" class="h-4 w-4"></i> Request time off
        </a>
    </div>
</div>
