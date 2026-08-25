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
                <form method="GET" action="{{ route('hr-documents.index') }}" class="flex items-center gap-2">
                    @if($showArchived)<input type="hidden" name="archived" value="1">@endif
                    <label class="text-xs font-semibold text-slate-500">Month</label>
                    <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white text-sm px-3 py-1.5 dark:bg-slate-900 dark:border-slate-600">
                    @if($month)
                        <a href="{{ route('hr-documents.index', $showArchived ? ['archived' => 1] : []) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600">Clear</a>
                    @endif
                </form>
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-700">
                    <a href="{{ route('hr-documents.index', $month ? ['month' => $month] : []) }}" class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $showArchived ? 'text-slate-500 hover:text-slate-700' : 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' }}">Active</a>
                    <a href="{{ route('hr-documents.index', array_filter(['archived' => 1, 'month' => $month])) }}" class="rounded-md px-3 py-1 text-xs font-semibold transition {{ $showArchived ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-500 hover:text-slate-700' }}">Archived @if($archivedCount) ({{ $archivedCount }}) @endif</a>
                </div>
                <a href="{{ route('hr-documents.deleted') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Deleted</a>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            @if($documents->isEmpty())
                <div class="p-8 text-center text-sm text-slate-500">
                    @if($month)
                        No {{ $showArchived ? 'archived ' : '' }}documents for {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}.
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
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Created</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($documents as $doc)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40">
                                <td class="px-5 py-3 font-semibold text-slate-800 dark:text-slate-200">{{ optional($doc->employee)->full_name ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $doc->template_name }}</td>
                                <td class="px-5 py-3 text-slate-500">{{ optional($doc->period_start)->format('M Y') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @if($doc->status === 'completed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">Completed</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">Draft</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-500">{{ $doc->created_at->format('d M Y') }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('hr-documents.show', $doc) }}" class="rounded-lg p-2 text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition dark:hover:bg-brand-500/10" title="View"><i data-lucide="eye" class="h-4 w-4"></i></a>
                                        <a href="{{ route('hr-documents.pdf', $doc) }}" class="rounded-lg p-2 text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition dark:hover:bg-brand-500/10" title="Download PDF"><i data-lucide="download" class="h-4 w-4"></i></a>
                                        @if($doc->archived_at)
                                            <form method="POST" action="{{ route('hr-documents.unarchive', $doc) }}">
                                                @csrf
                                                <button class="rounded-lg p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition dark:hover:bg-emerald-500/10" title="Restore from archive"><i data-lucide="archive-restore" class="h-4 w-4"></i></button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('hr-documents.archive', $doc) }}">
                                                @csrf
                                                <button class="rounded-lg p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition dark:hover:bg-amber-500/10" title="Archive"><i data-lucide="archive" class="h-4 w-4"></i></button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('hr-documents.destroy', $doc) }}" onsubmit="return confirm('Move this document to Deleted? You can restore it later.')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition dark:hover:bg-rose-500/10" title="Delete (recoverable)"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</div>
@endsection
