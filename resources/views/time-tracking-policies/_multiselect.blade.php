{{-- Reusable searchable multi-select bound to a parent Alpine array.
     Props: $label, $hint, $model (array var), $options (options var), $name (input name) --}}
<div>
    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">{{ $label }}</label>
    <p class="text-[11px] text-slate-400 mb-2">{{ $hint }}</p>
    <div x-data="{ open: false, q: '' }" @click.outside="open = false" class="relative">
        <button type="button" @click="open = !open" class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
            <span :class="{{ $model }}.length ? 'text-slate-700 dark:text-white' : 'text-slate-400'" x-text="{{ $model }}.length ? {{ $model }}.length + ' selected' : 'Select…'"></span>
            <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
        </button>
        <div x-show="open" x-cloak x-transition.origin.top class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
            <input x-model="q" type="text" placeholder="Search…" class="w-full rounded-lg border-slate-300 text-xs py-1.5 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            <div class="max-h-44 overflow-y-auto mt-2 space-y-0.5">
                <template x-for="opt in {{ $options }}.filter(o => o.name.toLowerCase().includes(q.toLowerCase()))" :key="opt.id">
                    <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="{{ $name }}[]" :value="opt.id" x-model="{{ $model }}" class="rounded border-slate-300 text-brand-600">
                        <span x-text="opt.name"></span>
                    </label>
                </template>
                <p x-show="!{{ $options }}.length" class="text-xs text-slate-400 px-2 py-1">Nothing configured.</p>
            </div>
        </div>
    </div>
    <div class="flex flex-wrap gap-1.5 mt-2" x-show="{{ $model }}.length" x-cloak>
        <template x-for="id in {{ $model }}" :key="id">
            <span class="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                <span x-text="optName({{ $options }}, id)"></span>
                <button type="button" @click="{{ $model }} = removeId({{ $model }}, id)" class="text-brand-400 hover:text-rose-500 text-sm leading-none">&times;</button>
            </span>
        </template>
    </div>
</div>
