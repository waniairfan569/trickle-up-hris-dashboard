{{-- Out-of-office footer + modal. Lives inside the celebrationsWidget() Alpine scope. --}}
<button type="button" @click="oooOpen = true; $nextTick(() => window.lucide && lucide.createIcons())"
        class="border-t border-slate-100 pt-3 mt-3 flex items-center justify-between dark:border-slate-700 w-full text-left rounded-b-lg hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition">
    <div class="text-xs text-slate-600 font-medium dark:text-slate-400">
        <span class="font-bold text-slate-800 dark:text-white" x-text="oooOnDate().length"></span>
        <span x-text="oooOnDate().length === 1 ? 'employee' : 'employees'"></span> out of office
    </div>
    <div class="flex items-center gap-2">
        <div class="flex -space-x-2">
            <template x-for="(o, i) in oooOnDate().slice(0, 3)" :key="'av' + i">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full ring-2 ring-white dark:ring-slate-800 overflow-hidden bg-gradient-to-br from-brand-400 to-indigo-500 text-white text-[9px] font-bold">
                    <template x-if="o.avatar"><img :src="o.avatar" class="h-full w-full object-cover"></template>
                    <template x-if="!o.avatar"><span x-text="o.initials"></span></template>
                </span>
            </template>
        </div>
        <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400"></i>
    </div>
</button>

@include('dashboard.partials.ooo-modal')
