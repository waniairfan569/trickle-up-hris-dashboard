@php
    $id  = $field['id'];
    $w   = ($field['width'] ?? 'full') === 'half' ? 'col-span-2 sm:col-span-1' : 'col-span-2';
    $inp = 'w-full rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 focus:border-brand-500 focus:ring-brand-500';
@endphp

@if(($field['type'] ?? '') === 'note')
    <div class="col-span-2">
        <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300 flex gap-2">
            <i data-lucide="flag" class="h-4 w-4 shrink-0 mt-0.5"></i><span>{{ $field['text'] ?? '' }}</span>
        </div>
    </div>
@else
    <div class="{{ $w }}">
        <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">{{ $field['label'] }}</label>

        @switch($field['type'])
            @case('textarea')
                <textarea rows="3" x-model="values['{{ $id }}']" class="{{ $inp }}"></textarea>
                @break

            @case('date')
                <input type="date" x-model="values['{{ $id }}']" class="{{ $inp }}">
                @break

            @case('number')
                <input type="number" step="any" x-model="values['{{ $id }}']" placeholder="{{ $field['placeholder'] ?? '' }}" class="{{ $inp }}">
                @break

            @case('select')
                <select x-model="values['{{ $id }}']" class="{{ $inp }}">
                    <option value="">— Select —</option>
                    @foreach($field['options'] ?? [] as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
                @break

            @case('radio')
                <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1.5">
                    @foreach($field['options'] ?? [] as $opt)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 cursor-pointer">
                            <input type="radio" x-model="values['{{ $id }}']" value="{{ $opt }}" class="text-brand-500 border-slate-300 focus:ring-brand-500"> {{ $opt }}
                        </label>
                    @endforeach
                </div>
                @break

            @case('checkbox')
                <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1.5">
                    @foreach($field['options'] ?? [] as $opt)
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 cursor-pointer">
                            <input type="checkbox" :checked='has(@js($id), @js($opt))' @change='toggle(@js($id), @js($opt))' class="rounded text-brand-500 border-slate-300 focus:ring-brand-500"> {{ $opt }}
                        </label>
                    @endforeach
                </div>
                @break

            @case('table')
                <div class="overflow-x-auto border border-slate-200 rounded-xl dark:border-slate-600">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-900/40 text-left text-xs font-semibold text-slate-500">
                                @foreach($field['columns'] ?? [] as $col)
                                    <th class="px-3 py-2 border-b border-slate-200 dark:border-slate-600">{{ $col }}</th>
                                @endforeach
                                <th class="w-10 border-b border-slate-200 dark:border-slate-600"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, ri) in (values['{{ $id }}'] || [])" :key="ri">
                                <tr>
                                    @foreach($field['columns'] ?? [] as $col)
                                        <td class="p-1 border-b border-slate-100 dark:border-slate-700 align-top">
                                            <input type="text" x-model='row[@js($col)]' class="w-full rounded-md border-slate-200 text-sm dark:bg-slate-900 dark:border-slate-600 focus:border-brand-500 focus:ring-brand-500">
                                        </td>
                                    @endforeach
                                    <td class="text-center border-b border-slate-100 dark:border-slate-700">
                                        <button type="button" @click="removeRow('{{ $id }}', ri)" class="p-1.5 text-slate-300 hover:text-rose-600"><i data-lucide="x" class="h-4 w-4"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="!(values['{{ $id }}'] || []).length">
                                <tr><td colspan="{{ count($field['columns'] ?? []) + 1 }}" class="px-3 py-3 text-center text-xs text-slate-400">No rows yet.</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click='addRow(@js($id), @js($field["columns"] ?? []))' class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add row
                </button>
                @break

            @case('signature')
                <div x-data="{}" x-init="$nextTick(() => initPad($refs.pad, '{{ $id }}'))">
                    <canvas x-ref="pad" width="600" height="150" class="w-full h-[130px] bg-white border border-slate-300 rounded-lg touch-none dark:border-slate-600" style="touch-action:none;"></canvas>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <input type="text" x-model="values['{{ $id }}'].typed" @input="typeSign($refs.pad, '{{ $id }}')" placeholder="…or type to sign" class="flex-1 min-w-[140px] rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600">
                        <button type="button" @click="clearPad($refs.pad, '{{ $id }}')" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"><i data-lucide="eraser" class="h-3.5 w-3.5"></i> Clear</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <input type="text" x-model="values['{{ $id }}'].name" placeholder="Name (printed)" class="rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600">
                        <input type="date" x-model="values['{{ $id }}'].date" class="rounded-lg border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600">
                    </div>
                </div>
                @break

            @default
                <input type="text" x-model="values['{{ $id }}']" placeholder="{{ $field['placeholder'] ?? '' }}" class="{{ $inp }}">
        @endswitch
    </div>
@endif
