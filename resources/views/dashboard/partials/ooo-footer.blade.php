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

<!-- Out-of-office modal -->
<div x-show="oooOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
    <div class="absolute inset-0 bg-slate-900/50" @click="oooOpen = false"></div>
    <div class="relative w-full max-w-xl rounded-2xl bg-white shadow-xl dark:bg-slate-800 flex flex-col max-h-[85vh] text-left">
        <div class="flex items-start justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <div>
                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white" x-text="displayDate()"></h2>
                <p class="text-xs text-slate-500 dark:text-slate-400"><span x-text="oooOnDate().length"></span> out of office</p>
            </div>
            <button type="button" @click="oooOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>

        <div class="px-6 pt-3 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-4">
                <button type="button" @click="oooTab = 'leave'" class="text-sm pb-2 border-b-2 flex items-center gap-1.5" :class="oooTab === 'leave' ? 'font-bold text-slate-800 border-slate-800 dark:text-white dark:border-white' : 'font-medium text-slate-400 border-transparent hover:text-slate-600'">
                    On leave <span class="bg-slate-100 text-slate-500 text-[10px] px-1.5 py-0.5 rounded-md dark:bg-slate-700 dark:text-slate-300" x-text="oooOnDate().length"></span>
                </button>
            </div>
            <div class="relative">
                <i data-lucide="search" class="h-4 w-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input x-model="oooSearch" type="text" placeholder="Search…" class="rounded-lg border-slate-300 pl-8 pr-3 py-1.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white w-40">
            </div>
        </div>

        <div class="border-t border-slate-100 dark:border-slate-700/60 overflow-y-auto">
            <!-- On leave -->
            <div x-show="oooTab === 'leave'">
                <template x-if="oooFiltered().length === 0"><p class="px-6 py-10 text-center text-sm text-slate-400">No one on leave on this day.</p></template>
                <template x-for="(o, i) in oooFiltered()" :key="'leave' + i">
                    <div class="flex items-center gap-3 px-6 py-3 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                        <span class="h-10 w-10 flex-shrink-0 inline-flex items-center justify-center rounded-full overflow-hidden bg-gradient-to-br from-brand-400 to-indigo-500 text-white text-xs font-bold">
                            <template x-if="o.avatar"><img :src="o.avatar" class="h-full w-full object-cover"></template>
                            <template x-if="!o.avatar"><span x-text="o.initials"></span></template>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate" x-text="o.name"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400" x-text="o.range"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
