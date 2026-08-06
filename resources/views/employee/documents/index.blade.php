@extends('layouts.hr-app')

@section('title', 'Document Library')
@section('breadcrumb', 'Document Library')

@section('content')
<div class="max-w-6xl mx-auto space-y-6"
     x-data="{ search: '', cat: 'all' }">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Document Library</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Sign documents sent to you and browse company documents.</p>
    </div>

    {{-- Awaiting your signature --}}
    @if(!empty($toSign) && $toSign->count())
        <div class="rounded-2xl border border-brand-200 bg-brand-50/50 shadow-sm p-5 dark:border-brand-500/30 dark:bg-brand-500/10">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-500/20"><i data-lucide="file-signature" class="h-4.5 w-4.5"></i></span>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Awaiting your signature ({{ $toSign->count() }})</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($toSign as $req)
                    <a href="{{ route('documents.sign', $req) }}" class="flex items-center justify-between gap-3 bg-white rounded-xl border border-slate-200/80 shadow-sm p-4 hover:border-brand-300 transition dark:bg-slate-800 dark:border-slate-700">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ optional($req->template)->name ?? 'Document' }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">Please review &amp; sign</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-slate-900 shrink-0">Sign <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Search + category filter -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
            <i data-lucide="search" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" x-model="search" placeholder="Search documents…" class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-800 dark:border-slate-600 dark:text-white">
        </div>
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
            <button @click="cat='all'" :class="cat==='all' ? 'bg-brand-600 text-slate-900' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300'" class="shrink-0 rounded-full px-4 py-1.5 text-sm font-bold">All</button>
            @foreach($categories as $cat)
                <button @click="cat='{{ $cat->id }}'" :class="cat==='{{ $cat->id }}' ? 'bg-brand-600 text-slate-900' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300'" class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-bold"><i data-lucide="{{ $cat->icon ?: 'folder' }}" class="h-3.5 w-3.5"></i> {{ $cat->name }}</button>
            @endforeach
        </div>
    </div>

    @if($documents->isEmpty())
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center dark:bg-slate-800 dark:border-slate-700">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="folder-open" class="h-7 w-7"></i></div>
            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No documents available</p>
            <p class="text-xs text-slate-400 mt-1">Nothing has been shared with you yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($documents as $doc)
                @php $fi = $doc->file_icon; $isPdf = strtolower((string)$doc->file_extension) === 'pdf'; @endphp
                <div data-name="{{ Str::lower($doc->title.' '.$doc->description) }}" data-cat="{{ $doc->category_id }}"
                     x-show="(cat==='all' || cat==='{{ $doc->category_id }}') && (search==='' || $el.dataset.name.includes(search.toLowerCase()))"
                     class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700 flex flex-col">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $fi['color'] }} shrink-0"><i data-lucide="{{ $fi['icon'] }}" class="h-5 w-5"></i></div>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-slate-800 dark:text-white truncate">{{ $doc->title }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ optional($doc->category)->name }} · v{{ $doc->version }} · {{ $doc->file_size_label }}</p>
                        </div>
                    </div>
                    @if($doc->description)<p class="text-sm text-slate-500 dark:text-slate-400 mt-3 line-clamp-2 flex-1">{{ $doc->description }}</p>@else<div class="flex-1"></div>@endif
                    @if($doc->requires_acknowledgment)
                        @php $ack = $acks[$doc->id] ?? null; @endphp
                        @if($ack)
                            <div class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-500/10">
                                <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                                <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400">Acknowledged on {{ $ack->acknowledged_at->format('d M Y') }}</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('document-library.acknowledge', $doc) }}" class="mt-3">
                                @csrf
                                <label class="flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 cursor-pointer hover:bg-amber-100/70 dark:bg-amber-500/10 dark:hover:bg-amber-500/20">
                                    <input type="checkbox" onchange="if(this.checked){ this.disabled=true; this.form.submit(); }" class="mt-0.5 h-4 w-4 rounded border-amber-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-[11px] font-semibold text-amber-800 dark:text-amber-300">I acknowledge that I have read and understood this document.</span>
                                </label>
                            </form>
                        @endif
                    @endif
                    <div class="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <a href="{{ route('document-library.download', $doc) }}" class="inline-flex items-center justify-center gap-1.5 flex-1 rounded-xl bg-brand-600 px-3 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="download" class="h-4 w-4"></i> Download</a>
                        @if($isPdf)
                            <a href="{{ route('document-library.read', $doc) }}" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200"><i data-lucide="eye" class="h-4 w-4"></i> View</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
