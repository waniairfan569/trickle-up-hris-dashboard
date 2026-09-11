@extends('layouts.hr-app')

@section('title', 'Documents')
@section('breadcrumb', 'Documents')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="file-signature" class="h-6 w-6 text-brand-500"></i> Documents
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create fillable forms (lateness reviews, return-to-work, …), auto-fill from attendance, sign, and keep on file.</p>
        </div>
        <a href="{{ route('hr-documents.templates.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 transition">
            <i data-lucide="plus" class="h-4 w-4"></i> New template
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
    @endif

    {{-- Templates --}}
    <section>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Templates</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($templates as $tpl)
                <div class="group relative bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 flex flex-col dark:bg-slate-800 dark:border-slate-700">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 shrink-0 rounded-xl bg-brand-50 text-brand-600 grid place-items-center dark:bg-brand-500/10">
                            <i data-lucide="{{ $tpl->icon ?: 'file-text' }}" class="h-5 w-5"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-slate-900 dark:text-white truncate">{{ $tpl->name }}</div>
                            @if($tpl->is_system)
                                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Built-in</span>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-3 flex-1">{{ $tpl->description }}</p>
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('hr-documents.create', ['template' => $tpl->id]) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700 transition dark:bg-white dark:text-slate-900">
                            <i data-lucide="pen-line" class="h-3.5 w-3.5"></i> New document
                        </a>
                        <a href="{{ route('hr-documents.templates.edit', $tpl) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                        </a>
                        @unless($tpl->is_system)
                            <form method="POST" action="{{ route('hr-documents.templates.destroy', $tpl) }}" onsubmit="return confirm('Delete this template? Documents already created keep their copy.')" class="ml-auto">
                                @csrf @method('DELETE')
                                <button class="inline-flex items-center rounded-lg border border-transparent p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                            </form>
                        @endunless
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-600">
                    No templates yet. <a href="{{ route('hr-documents.templates.create') }}" class="text-brand-600 font-semibold">Create your first template</a>.
                </div>
            @endforelse
        </div>
    </section>

    {{-- History --}}
    <section>
        <div class="flex items-center justify-between gap-4 flex-wrap mb-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $showArchived ? 'Archived documents' : 'Recent documents' }}</h2>
            <div class="flex items-center gap-3">
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                    <a href="{{ route('hr-documents.index', $filters) }}" class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $showArchived ? 'text-slate-500 hover:text-slate-700' : 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' }}">Active</a>
                    <a href="{{ route('hr-documents.index', ['archived' => 1] + $filters) }}" class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $showArchived ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-500 hover:text-slate-700' }}">Archived @if($archivedCount) ({{ $archivedCount }}) @endif</a>
                </div>
                <a href="{{ route('hr-documents.deleted') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Deleted</a>
            </div>
        </div>

        {{-- Search by employee / document + sent-date range --}}
        <form method="GET" action="{{ route('hr-documents.index') }}" class="flex flex-wrap items-center gap-2 mb-3">
            @if($showArchived)<input type="hidden" name="archived" value="1">@endif
            <div class="relative flex-1 min-w-[180px] max-w-xs">
                <i data-lucide="search" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search by employee or document…" class="w-full rounded-xl border border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div class="flex items-center gap-1 text-xs">
                <span class="text-slate-400 font-semibold">From</span>
                <input type="date" name="date_from" value="{{ $dateFrom }}" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-2 py-1.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <span class="text-slate-400 font-semibold">To</span>
                <input type="date" name="date_to" value="{{ $dateTo }}" onchange="this.form.submit()" class="rounded-xl border border-slate-300 px-2 py-1.5 text-xs dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <button type="submit" class="btn-dark btn-sm">Search</button>
            @if($filters)<a href="{{ route('hr-documents.index', $showArchived ? ['archived' => 1] : []) }}" class="btn-outline btn-sm">Clear</a>@endif
        </form>

        @php
            // Plain-language description of the current filter, e.g. "sent between 1 and 31 Aug 2026".
            $fmt = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M Y');
            $rangeText = $dateFrom && $dateTo ? "sent between {$fmt($dateFrom)} and {$fmt($dateTo)}"
                : ($dateFrom ? "sent since {$fmt($dateFrom)}" : ($dateTo ? "sent up to {$fmt($dateTo)}" : ''));
            $matchText = $search !== '' ? "matching “{$search}”" : '';
            $filterText = trim(implode(' · ', array_filter([$matchText, $rangeText])));
            // Month the document belongs to on this list: when it was sent, or created for drafts.
            $docDate = fn ($d) => $d->sent_at ?? ($d->status !== 'draft' && $d->first_signer_at ? \Illuminate\Support\Carbon::parse($d->first_signer_at) : $d->created_at);
        @endphp

        @if($filters && $documents->isNotEmpty())
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $documents->count() }}</span> {{ $showArchived ? 'archived ' : '' }}{{ Str::plural('document', $documents->count()) }} {{ $filterText }}
            </p>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            @if($documents->isEmpty())
                <div class="p-8 text-center text-sm text-slate-500">
                    @if($filters)
                        No {{ $showArchived ? 'archived ' : '' }}documents {{ $filterText }}.
                    @else
                        {{ $showArchived ? 'No archived documents.' : 'No documents on file yet.' }}
                    @endif
                </div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-100 dark:border-slate-700">
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Document</th>
                            <th class="px-5 py-3">Period</th>
                            <th class="px-5 py-3">Meeting</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Sent</th>
                            <th class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($documents->groupBy(fn ($d) => $docDate($d)->format('Y-m')) as $ym => $monthDocs)
                            {{-- Month header: the list is grouped by when each document was sent (created, for drafts) --}}
                            <tr class="bg-slate-50/80 dark:bg-slate-900/40">
                                <td colspan="7" class="px-5 py-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                    {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $ym)->format('F Y') }}
                                    <span class="ml-1 font-semibold normal-case tracking-normal text-slate-400/80">· {{ $monthDocs->count() }} {{ Str::plural('document', $monthDocs->count()) }}</span>
                                </td>
                            </tr>
                        @foreach($monthDocs as $doc)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40">
                                <td class="px-5 py-3 font-semibold text-slate-800 dark:text-slate-200">{{ optional($doc->employee)->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $doc->template_name }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($doc->period_start)->format('M Y') ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($doc->meeting_date)->format('d M Y') ?? '—' }}</td>
                                @php
                                    // Legacy rows were marked sent before sent_at existed — fall back to the first signer row.
                                    $sentAt = $doc->sent_at ?? ($doc->status !== 'draft' && $doc->first_signer_at ? \Illuminate\Support\Carbon::parse($doc->first_signer_at) : null);
                                @endphp
                                <td class="px-5 py-3">
                                    @if($doc->status === 'completed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">Completed</span>
                                    @elseif($doc->status === 'sent')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-400">Awaiting signature</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Draft</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500">
                                    @if($sentAt)
                                        <div class="text-slate-700 dark:text-slate-200">{{ $sentAt->format('d M Y') }} <span class="text-[11px] text-slate-400">{{ $sentAt->format('g:i A') }}</span></div>
                                        {{-- Who it went to and where each signature stands --}}
                                        @foreach($doc->signers as $signer)
                                            <div class="mt-0.5 flex items-center gap-1 text-[11px] {{ $signer->signed_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                                <i data-lucide="{{ $signer->signed_at ? 'check-circle-2' : 'clock' }}" class="h-3 w-3 shrink-0"></i>
                                                <span>to {{ optional($signer->user)->full_name ?? 'Signer' }}@if($signer->role === 'manager') (manager)@endif — {{ $signer->signed_at ? 'signed ' . $signer->signed_at->format('d M') : 'not signed yet' }}</span>
                                            </div>
                                        @endforeach
                                    @else
                                        <span class="text-slate-400">Not sent</span>
                                        <span class="block text-[11px] text-slate-400">Created {{ $doc->created_at->format('d M Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    {{-- Actions live in a ⋮ menu (teleported to <body> so the table's overflow-hidden doesn't clip it) --}}
                                    <div class="inline-block text-left" x-data="{
                                        open: false, style: '',
                                        toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => { this.place(); if (window.lucide) lucide.createIcons(); }); },
                                        place() {
                                            const r = this.$refs.btn.getBoundingClientRect(), W = 184, H = 200;
                                            let left = r.right - W; if (left < 8) left = 8;
                                            let top = (r.bottom + H > window.innerHeight) ? (r.top - H - 4) : (r.bottom + 4);
                                            this.style = `position:fixed;left:${left}px;top:${top}px;width:${W}px;`;
                                        }
                                    }">
                                        <button type="button" x-ref="btn" @click.stop="toggle()" title="Actions"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-white"><i data-lucide="more-vertical" class="h-4 w-4"></i></button>
                                        <template x-teleport="body">
                                            <div x-show="open" x-cloak x-transition.opacity.duration.100ms @click.outside="open = false" @keydown.escape.window="open = false"
                                                 :style="style" class="z-[60] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:bg-slate-800 dark:border-slate-700">
                                                <a href="{{ route('hr-documents.show', $doc) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-3.5 w-3.5"></i> View</a>
                                                <a href="{{ route('hr-documents.edit', $doc) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</a>
                                                <a href="{{ route('hr-documents.pdf', $doc) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="download" class="h-3.5 w-3.5"></i> Download PDF</a>
                                                @if($doc->archived_at)
                                                    <form method="POST" action="{{ route('hr-documents.unarchive', $doc) }}">@csrf<button type="submit" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-500/10"><i data-lucide="archive-restore" class="h-3.5 w-3.5"></i> Restore from archive</button></form>
                                                @else
                                                    <form method="POST" action="{{ route('hr-documents.archive', $doc) }}">@csrf<button type="submit" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700"><i data-lucide="archive" class="h-3.5 w-3.5"></i> Archive</button></form>
                                                @endif
                                                <form method="POST" action="{{ route('hr-documents.destroy', $doc) }}" onsubmit="return confirm('Move this document to Deleted? You can restore it later.')">@csrf @method('DELETE')<button type="submit" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete</button></form>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</div>
@endsection
