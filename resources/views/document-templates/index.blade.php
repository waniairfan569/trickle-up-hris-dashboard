@extends('layouts.hr-app')

@section('title', 'Document Templates')
@section('breadcrumb', 'Document Templates')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{ search: @js($search) }">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Document Templates</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reusable contracts, NDAs and letters — pre-filled with employee data and sent for signature.</p>
        </div>
        <a href="{{ route('document-templates.create') }}"
           class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition shrink-0">
            <i data-lucide="plus" class="h-4 w-4"></i> Add template
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i><span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Search -->
    <div class="relative">
        <i data-lucide="search" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" x-model="search"
               placeholder="Search templates…"
               class="w-full rounded-xl border-slate-300 pl-10 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
    </div>

    <!-- Active templates -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        @forelse($templates as $tpl)
            <div x-show="search === '' || $el.dataset.name.includes(search.toLowerCase())"
                 data-name="{{ strtolower($tpl->name) }}"
                 class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10">
                    <i data-lucide="file-signature" class="h-5 w-5"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('document-templates.edit', $tpl) }}" class="text-sm font-bold text-slate-800 hover:text-brand-600 dark:text-white truncate block">{{ $tpl->name }}</a>
                    @if($tpl->description)<p class="text-xs text-slate-400 truncate">{{ $tpl->description }}</p>@endif
                    <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                        Modified on {{ $tpl->updated_at->format('d M Y') }}
                        @if($tpl->entities_count > 0)
                            <span class="text-slate-300 dark:text-slate-600">|</span> Applied in {{ $tpl->entities_count }} {{ Str::plural('location', $tpl->entities_count) }}
                        @endif
                        @if($tpl->version > 1)
                            <span class="text-slate-300 dark:text-slate-600">|</span> v{{ $tpl->version }}
                        @endif
                    </p>
                    @if(!empty($tpl->tags))
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            @foreach($tpl->tags as $tag)
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Three-dot menu -->
                <div class="relative flex-shrink-0" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                        <i data-lucide="more-vertical" class="h-5 w-5"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                        <a href="{{ route('document-templates.edit', $tpl) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                            <i data-lucide="pencil" class="h-4 w-4 text-slate-400"></i> Edit
                        </a>
                        @if($tpl->isPdf())
                            <a href="{{ route('document-templates.send-form', $tpl) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                <i data-lucide="send" class="h-4 w-4 text-slate-400"></i> Send for signature
                            </a>
                            <a href="{{ route('document-templates.generate', $tpl) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                <i data-lucide="file-down" class="h-4 w-4 text-slate-400"></i> Generate document
                            </a>
                        @endif
                        <a href="{{ route('document-templates.preview-view', $tpl) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                            <i data-lucide="eye" class="h-4 w-4 text-slate-400"></i> Preview
                        </a>
                        <form action="{{ route('document-templates.archive', $tpl) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                <i data-lucide="archive" class="h-4 w-4 text-slate-400"></i> Archive
                            </button>
                        </form>
                        <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                        <form action="{{ route('document-templates.destroy', $tpl) }}" method="POST" onsubmit="return confirm('Delete “{{ $tpl->name }}” permanently? This cannot be undone.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="file-signature" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No document templates yet</p>
                <p class="text-xs text-slate-400 mt-1">Create your first template to pre-fill and send documents for signature.</p>
            </div>
        @endforelse
    </div>

    <!-- Archived templates -->
    @if($archived->count())
        <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-6 py-4 text-left">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Archived Document Templates ({{ $archived->count() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak class="border-t border-slate-100 dark:border-slate-700/60">
                @foreach($archived as $tpl)
                    <div class="flex items-center gap-4 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-700">
                            <i data-lucide="file-signature" class="h-4 w-4"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-600 dark:text-slate-300 truncate">{{ $tpl->name }}</p>
                            <p class="text-xs text-slate-400 truncate">Archived · Modified on {{ $tpl->updated_at->format('d M Y') }}</p>
                        </div>
                        <div class="relative flex-shrink-0" x-data="{ open: false }">
                            <button @click="open = !open" @click.outside="open = false" type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700">
                                <i data-lucide="more-vertical" class="h-5 w-5"></i>
                            </button>
                            <div x-show="open" x-cloak x-transition
                                 class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                                <a href="{{ route('document-templates.edit', $tpl) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                    <i data-lucide="pencil" class="h-4 w-4 text-slate-400"></i> Edit
                                </a>
                                <a href="{{ route('document-templates.preview', $tpl) }}" target="_blank" class="flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                    <i data-lucide="eye" class="h-4 w-4 text-slate-400"></i> Preview
                                </a>
                                <form action="{{ route('document-templates.restore', $tpl) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                        <i data-lucide="rotate-ccw" class="h-4 w-4 text-slate-400"></i> Unarchive
                                    </button>
                                </form>
                                <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                <form action="{{ route('document-templates.destroy', $tpl) }}" method="POST" onsubmit="return confirm('Delete “{{ $tpl->name }}” permanently? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                                        <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
