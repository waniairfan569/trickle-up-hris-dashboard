{{-- Read-only render of a filled HR document. Expects $document and $data. --}}
<div class="space-y-5">
    @foreach($document->schema as $section)
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <div class="bg-slate-900 px-5 py-3"><h3 class="text-sm font-bold text-white tracking-wide">{{ $section['title'] }}</h3></div>
            <div class="p-5 grid grid-cols-2 gap-x-5 gap-y-4">
                @foreach($section['fields'] as $field)
                    @php $v = $data[$field['id']] ?? null; $half = ($field['width'] ?? 'full') === 'half'; @endphp

                    @if(($field['type'] ?? '') === 'note')
                        <div class="col-span-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300">{{ $field['text'] ?? '' }}</div>
                    @elseif(($field['type'] ?? '') === 'signature')
                        <div class="{{ $half ? 'col-span-2 sm:col-span-1' : 'col-span-2' }}">
                            <div class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">{{ $field['label'] }}</div>
                            <div class="border border-slate-200 rounded-lg p-3 dark:border-slate-600">
                                @if(is_array($v) && !empty($v['image']))
                                    <img src="{{ $v['image'] }}" alt="signature" class="h-16 object-contain">
                                @else
                                    <div class="h-16 grid place-items-center text-xs text-slate-300">Not signed</div>
                                @endif
                                <div class="mt-2 text-xs text-slate-500 border-t border-slate-100 pt-2 dark:border-slate-700">
                                    {{ is_array($v) ? ($v['name'] ?? '') : '' }}
                                    @if(is_array($v) && !empty($v['date'])) · {{ \Illuminate\Support\Carbon::parse($v['date'])->format('d M Y') }} @endif
                                </div>
                            </div>
                        </div>
                    @elseif(($field['type'] ?? '') === 'table')
                        <div class="col-span-2">
                            <div class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">{{ $field['label'] }}</div>
                            <div class="overflow-x-auto border border-slate-200 rounded-lg dark:border-slate-600">
                                <table class="w-full text-sm">
                                    <thead><tr class="bg-slate-50 text-left text-xs font-semibold text-slate-500 dark:bg-slate-900/40">
                                        @foreach($field['columns'] ?? [] as $col)<th class="px-3 py-2">{{ $col }}</th>@endforeach
                                    </tr></thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        @forelse(is_array($v) ? $v : [] as $row)
                                            <tr>@foreach($field['columns'] ?? [] as $col)<td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $row[$col] ?? '' }}</td>@endforeach</tr>
                                        @empty
                                            <tr><td colspan="{{ count($field['columns'] ?? []) }}" class="px-3 py-3 text-center text-xs text-slate-400">—</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="{{ $half ? 'col-span-2 sm:col-span-1' : 'col-span-2' }}">
                            <div class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">{{ $field['label'] }}</div>
                            <div class="text-sm text-slate-800 dark:text-slate-100 min-h-[1.25rem] whitespace-pre-line">{{ is_array($v) ? implode(', ', $v) : ($v ?: '—') }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>
