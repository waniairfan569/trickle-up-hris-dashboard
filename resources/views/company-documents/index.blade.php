@extends('layouts.hr-app')

@section('title', 'Company Documents')
@section('breadcrumb', 'Company Documents')

@php
    $accessBadge = [
        'company_wide' => ['Company-wide', 'bg-emerald-50 text-emerald-700'],
        'department' => ['Department', 'bg-sky-50 text-sky-700'],
        'specific_users' => ['Specific', 'bg-amber-50 text-amber-700'],
    ];
@endphp

@section('content')
<div class="max-w-7xl mx-auto space-y-6"
     x-data="{
        version: { open: false, id: null, title: '', action: '' },
        openVersion(id, title) { this.version = { open: true, id, title, action: '/company-documents/' + id + '/new-version' }; },
     }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Company Documents</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Upload, organize and control access to company files.</p>
        </div>
        <a href="{{ route('company-documents.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700"><i data-lucide="upload" class="h-4 w-4"></i> Add document</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>@endif

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([['Total documents', $stats['total'], 'files', 'text-slate-600'], ['Downloads this month', $stats['downloads_month'], 'download', 'text-brand-600'], ['Expiring soon', $stats['expiring'], 'alarm-clock', 'text-amber-600'], ['Categories', $stats['categories'], 'folders', 'text-violet-600']] as [$label, $val, $icon, $color])
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700">
                <div class="flex items-center gap-2 {{ $color }}"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i><span class="text-xs font-bold uppercase tracking-wider">{{ $label }}</span></div>
                <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $val }}</p>
            </div>
        @endforeach
    </div>

    <!-- Category filter -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
        <a href="{{ route('company-documents.admin') }}" class="shrink-0 rounded-full px-4 py-1.5 text-sm font-bold {{ !request('category') ? 'bg-brand-600 text-slate-900' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' }}">All</a>
        @foreach($categories as $cat)
            <a href="{{ route('company-documents.admin', ['category' => $cat->id]) }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-bold {{ request('category') == $cat->id ? 'bg-brand-600 text-slate-900' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' }}">
                <i data-lucide="{{ $cat->icon ?: 'folder' }}" class="h-3.5 w-3.5"></i> {{ $cat->name }} <span class="text-xs opacity-60">{{ $cat->documents_count }}</span>
            </a>
        @endforeach
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/40 text-xs font-bold text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="text-left px-5 py-3">Document</th>
                        <th class="text-left px-5 py-3">Category</th>
                        <th class="text-left px-5 py-3">Access</th>
                        <th class="text-left px-5 py-3">Ver.</th>
                        <th class="text-left px-5 py-3">Size</th>
                        <th class="text-left px-5 py-3">Downloads</th>
                        <th class="text-left px-5 py-3">Expires</th>
                        <th class="text-right px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($documents as $doc)
                        @php $fi = $doc->file_icon; [$accLabel, $accClass] = $accessBadge[$doc->access_level] ?? ['—','bg-slate-100 text-slate-600']; @endphp
                        <tr class="{{ !$doc->is_active ? 'opacity-50' : '' }}">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg {{ $fi['color'] }} shrink-0"><i data-lucide="{{ $fi['icon'] }}" class="h-4.5 w-4.5"></i></div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 dark:text-white truncate">{{ $doc->title }}</p>
                                        <p class="text-xs text-slate-400 truncate">{{ $doc->file_name }} · by {{ optional($doc->uploader)->full_name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3"><span class="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300"><i data-lucide="{{ optional($doc->category)->icon ?: 'folder' }}" class="h-3.5 w-3.5"></i> {{ optional($doc->category)->name }}</span></td>
                            <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $accClass }}">{{ $accLabel }}</span></td>
                            <td class="px-5 py-3 text-slate-500">v{{ $doc->version }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $doc->file_size_label }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $doc->download_count }}</td>
                            <td class="px-5 py-3">
                                @if($doc->expires_at)
                                    <span class="text-xs font-semibold {{ $doc->is_expired ? 'text-rose-600' : 'text-slate-500' }}">{{ $doc->expires_at->format('d M Y') }}</span>
                                @else <span class="text-slate-300">—</span> @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('document-library.view', $doc) }}" target="_blank" title="View" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="eye" class="h-4 w-4"></i></a>
                                    <a href="{{ route('document-library.download', $doc) }}" title="Download" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="download" class="h-4 w-4"></i></a>
                                    <button @click="openVersion({{ $doc->id }}, $el.dataset.title)" data-title="{{ $doc->title }}" title="New version" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="git-branch" class="h-4 w-4"></i></button>
                                    <a href="{{ route('company-documents.edit', $doc) }}" title="Edit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-4 w-4"></i></a>
                                    <form action="{{ route('company-documents.destroy', $doc) }}" method="POST" onsubmit="return confirm('Delete “{{ $doc->title }}”?');">@csrf @method('DELETE')<button title="Delete" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-16 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="folder-open" class="h-7 w-7"></i></div>
                            <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No documents yet</p>
                            <p class="text-xs text-slate-400 mt-1">Upload your first company document.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- New version modal -->
    <div x-show="version.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,23,42,.5)" @click.self="version.open=false">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Upload new version</h3>
                <button @click="version.open=false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <p class="text-sm text-slate-500" x-text="version.title"></p>
            <form :action="version.action" method="POST" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">New file <span class="text-rose-500">*</span></label><input type="file" name="file" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700"></div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Version <span class="text-rose-500">*</span></label><input type="text" name="version" required placeholder="e.g. 2.0" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">What changed?</label><textarea name="version_notes" rows="2" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none"></textarea></div>
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Upload version</button>
            </form>
        </div>
    </div>
</div>
@endsection
