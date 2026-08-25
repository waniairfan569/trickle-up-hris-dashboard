@extends('layouts.hr-app')

@section('title', 'Events')
@section('breadcrumb', 'Events')

@section('content')
<style>
    [x-cloak]{display:none!important}
    .draft-badge{display:inline-block;margin-left:6px;padding:0 5px;border-radius:6px;background:#334155;color:#fff;font-size:9px;font-weight:700;vertical-align:middle}
    .fc .fc-daygrid-event{cursor:pointer}
    .fc .fc-toolbar-title{font-size:1rem;font-weight:800}
    .fc .fc-button{background:#fff;border-color:#e2e8f0;color:#334155;font-weight:700;text-transform:capitalize}
    .fc .fc-button-primary:not(:disabled).fc-button-active{background:#F5C800;border-color:#F5C800;color:#111}
    .dark .fc{--fc-border-color:#334155;--fc-page-bg-color:transparent;color:#e2e8f0}
    .dark .fc .fc-button{background:#1e293b;border-color:#334155;color:#e2e8f0}
    .dark .fc .fc-col-header-cell-cushion,.dark .fc .fc-daygrid-day-number{color:#94a3b8}
</style>

<div class="max-w-5xl mx-auto space-y-6"
     x-data="eventsPage()"
     x-on:event-updated.window="onEventUpdated($event.detail)"
     x-on:toast.window="toast = $event.detail; clearTimeout(window.__evToast); window.__evToast = setTimeout(() => toast = '', 2600)">

    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Events</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Plan events, then publish them to employees’ calendars. Drafts stay hidden until you publish.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i><span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="inline-flex rounded-xl border border-slate-200 dark:border-slate-700 p-1 bg-slate-50 dark:bg-slate-900/40">
        @foreach(['calendar' => ['calendar-days', 'Calendar View'], 'list' => ['list', 'List View'], 'add' => ['plus', 'Add Event']] as $key => [$icon, $label])
            <button type="button" @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold rounded-lg transition">
                <i data-lucide="{{ $icon }}" class="h-3.5 w-3.5"></i> {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ============================ CALENDAR TAB ============================ --}}
    <div x-show="tab === 'calendar'" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-5 dark:bg-slate-800 dark:border-slate-700">
        <div id="admin-calendar"></div>
        <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-slate-400">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#F5C800"></span> Published (visible to employees)</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-dashed border-slate-400" style="opacity:.55;background:#94a3b8"></span> Draft (hidden)</span>
            <span>Click an event to publish, pin or delete it.</span>
        </div>
    </div>

    {{-- ============================== LIST TAB ============================== --}}
    <div x-show="tab === 'list'" x-cloak class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('events.index') }}" class="flex items-center gap-1.5">
                <input type="hidden" name="tab" value="list">
                <label for="ev-month" class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Month</label>
                <input type="month" id="ev-month" name="month" value="{{ $month }}" onchange="this.form.submit()"
                       class="rounded-xl border border-slate-300 shadow-sm text-sm py-1.5 px-3 focus:border-brand-500 focus:ring-brand-500 dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                @if($month)
                    <a href="{{ route('events.index', ['tab' => 'list']) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-rose-600 dark:text-slate-400"><i data-lucide="x" class="h-3.5 w-3.5"></i> Clear</a>
                @endif
            </form>
            @if($month)<span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $events->count() }} active · {{ $archived->count() }} archived this month</span>@endif
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">All events</span></div>
            @if($events->isEmpty())
                <div class="flex flex-col items-center justify-center py-14 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="calendar-heart" class="h-7 w-7"></i></div>
                    <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No events yet</p>
                    <p class="text-xs text-slate-400 mt-1">Use the <button type="button" @click="tab='add'" class="font-bold text-brand-600">Add Event</button> tab to create one.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/40 text-[10px] uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="px-5 py-2.5 font-bold">Title</th>
                                <th class="px-5 py-2.5 font-bold">Date</th>
                                <th class="px-5 py-2.5 font-bold">Location</th>
                                <th class="px-5 py-2.5 font-bold">Status</th>
                                <th class="px-5 py-2.5 font-bold">Visibility</th>
                                <th class="px-5 py-2.5 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @foreach($events as $event)
                                <tr x-data="eventRow({ id: {{ $event->id }}, published: {{ $event->is_published ? 'true' : 'false' }}, pinned: {{ $event->is_pinned ? 'true' : 'false' }} })" class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30"
                                    x-on:open-event-edit.window="if (String($event.detail.id) === String(id)) { editOpen = true; $nextTick(() => window.lucide && window.lucide.createIcons()); }">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-white">
                                            <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background:{{ $event->color_hex }}"></span>
                                            <span class="truncate max-w-[16rem]">{{ $event->title }}</span>
                                            <i x-show="pinned" data-lucide="pin" class="h-3 w-3 text-brand-500" title="Pinned"></i>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                        {{ $event->date->format('d M Y') }}@if($event->is_multi_day) – {{ $event->end_date->format('d M Y') }}@endif
                                    </td>
                                    <td class="px-5 py-3 text-slate-500 dark:text-slate-400 max-w-[12rem] truncate">{{ $event->location ?: '—' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span x-show="published" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Published</span>
                                        <span x-show="!published" class="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:border-slate-600 dark:text-slate-400"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Draft</span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-slate-500 dark:text-slate-400 capitalize">
                                        @if($event->visibility === 'all') Everyone
                                        @elseif($event->visibility === 'department') Departments
                                        @else Specific
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            {{-- Publish / unpublish toggle (AJAX) --}}
                                            <button type="button" class="publish-toggle" :data-event-id="id" :data-published="published ? '1' : '0'"
                                                    @click="togglePublish()"
                                                    :class="published ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400'"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-bold transition">
                                                <i :data-lucide="published ? 'eye-off' : 'send'" class="h-3.5 w-3.5"></i>
                                                <span x-text="published ? 'Hide' : 'Publish'"></span>
                                            </button>
                                            <button type="button" @click="togglePin()" title="Pin / unpin"
                                                    :class="pinned ? 'text-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'text-slate-400 hover:text-brand-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                                                    class="inline-flex items-center rounded-lg p-1.5"><i data-lucide="pin" class="h-3.5 w-3.5"></i></button>
                                            <button type="button" @click="editOpen = true" title="Edit" class="inline-flex items-center rounded-lg p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-3.5 w-3.5"></i></button>
                                            <form action="{{ route('events.archive', $event) }}" method="POST">@csrf<button type="submit" title="Archive" class="inline-flex items-center rounded-lg p-1.5 text-slate-400 hover:text-amber-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="archive" class="h-3.5 w-3.5"></i></button></form>
                                            <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Delete “{{ $event->title }}”?');">@csrf @method('DELETE')<button type="submit" title="Delete" class="inline-flex items-center rounded-lg p-1.5 text-slate-400 hover:text-rose-500 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button></form>
                                        </div>

                                        {{-- Edit modal (teleported to body to avoid table-nesting issues) --}}
                                        <template x-teleport="body">
                                            <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 text-left">
                                                <div class="absolute inset-0 bg-slate-900/50" @click="editOpen = false"></div>
                                                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-slate-800 max-h-[90vh] overflow-y-auto">
                                                    <form action="{{ route('events.update', $event) }}" method="POST" x-data="{ vis: '{{ $event->visibility }}' }">
                                                        @csrf @method('PUT')
                                                        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                                                            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Edit event</h2>
                                                            <button type="button" @click="editOpen = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
                                                        </div>
                                                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                            <div class="sm:col-span-2"><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title</label><input type="text" name="title" value="{{ $event->title }}" required maxlength="120" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                                            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Date</label><input type="date" name="date" value="{{ $event->date->toDateString() }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                                            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">End date</label><input type="date" name="end_date" value="{{ optional($event->end_date)->toDateString() }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                                            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Location</label><input type="text" name="location" value="{{ $event->location }}" maxlength="120" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                                                            <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Color</label>
                                                                <select name="color" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                                    @foreach(['brand'=>'Yellow','indigo'=>'Indigo','emerald'=>'Green','rose'=>'Rose','sky'=>'Sky'] as $v=>$lbl)<option value="{{ $v }}" @selected($event->color===$v)>{{ $lbl }}</option>@endforeach
                                                                </select>
                                                            </div>
                                                            <div class="sm:col-span-2"><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description</label><textarea name="description" rows="2" maxlength="1000" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none">{{ $event->description }}</textarea></div>
                                                            <div class="sm:col-span-2">
                                                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Who can see it</label>
                                                                <select name="visibility" x-model="vis" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                                    <option value="all">Everyone</option>
                                                                    <option value="department">Specific departments</option>
                                                                    <option value="specific">Specific employees</option>
                                                                </select>
                                                                @php $eDepts = $event->audiences->where('audience_type','department')->pluck('audience_id')->all(); $eUsers = $event->audiences->where('audience_type','user')->pluck('audience_id')->all(); @endphp
                                                                <select x-show="vis==='department'" x-cloak name="department_ids[]" multiple size="4" class="mt-2 w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected(in_array($d->id,$eDepts))>{{ $d->name }}</option>@endforeach
                                                                </select>
                                                                <select x-show="vis==='specific'" x-cloak name="user_ids[]" multiple size="5" class="mt-2 w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                                                    @foreach($users as $u)<option value="{{ $u->id }}" @selected(in_array($u->id,$eUsers))>{{ trim($u->first_name.' '.$u->last_name) }}</option>@endforeach
                                                                </select>
                                                            </div>
                                                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                                                <input type="hidden" name="notify_employees" value="0"><input type="checkbox" name="notify_employees" value="1" @checked($event->notify_employees) class="rounded border-slate-300 text-brand-600"> Notify on publish
                                                            </label>
                                                            <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                                                <input type="hidden" name="is_pinned" value="0"><input type="checkbox" name="is_pinned" value="1" @checked($event->is_pinned) class="rounded border-slate-300 text-brand-600"> Pin to dashboard
                                                            </label>
                                                        </div>
                                                        <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                                                            <button type="button" @click="editOpen = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Archived --}}
        @if($archived->count())
            <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
                <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-6 py-4 text-left">
                    <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Archived events ({{ $archived->count() }})</span>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-cloak class="border-t border-slate-100 dark:border-slate-700/60">
                    @foreach($archived as $event)
                        <div class="flex items-center gap-4 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                            <div class="flex-1 min-w-0"><p class="text-sm font-bold text-slate-600 dark:text-slate-300 truncate">{{ $event->title }}</p><p class="text-xs text-slate-400">{{ $event->date->format('d M Y') }}</p></div>
                            <form action="{{ route('events.restore', $event) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Restore</button></form>
                            <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Delete permanently?');">@csrf @method('DELETE')<button type="submit" title="Delete" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button></form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ============================== ADD TAB =============================== --}}
    <div x-show="tab === 'add'" x-cloak class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-4">Add an event</h2>
        <form action="{{ route('events.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="{ vis: 'all' }">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title <span class="text-rose-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Town Hall Meeting" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Date <span class="text-rose-500">*</span></label>
                <input type="date" name="date" id="add-event-date" value="{{ old('date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">End date <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Location <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                <input type="text" name="location" value="{{ old('location') }}" maxlength="120" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Color</label>
                <select name="color" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="brand">Yellow</option><option value="indigo">Indigo</option><option value="emerald">Green</option><option value="rose">Rose</option><option value="sky">Sky</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Description <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                <textarea name="description" rows="2" maxlength="1000" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white resize-none">{{ old('description') }}</textarea>
            </div>

            {{-- Visibility --}}
            <div class="sm:col-span-2 rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-slate-700/60 dark:bg-slate-900/30 space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Who can see it</label>
                    <select name="visibility" x-model="vis" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                        <option value="all">Everyone</option>
                        <option value="department">Specific departments</option>
                        <option value="specific">Specific employees</option>
                    </select>
                </div>
                <select x-show="vis==='department'" x-cloak name="department_ids[]" multiple size="4" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
                <select x-show="vis==='specific'" x-cloak name="user_ids[]" multiple size="6" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-white">
                    @foreach($users as $u)<option value="{{ $u->id }}">{{ trim($u->first_name.' '.$u->last_name) }}</option>@endforeach
                </select>
                <p x-show="vis!=='all'" x-cloak class="text-[11px] text-slate-400">Hold Ctrl/⌘ to select more than one.</p>
            </div>

            {{-- Publish + notify toggles --}}
            <label class="inline-flex items-center gap-2.5 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">
                <input type="hidden" name="publish_now" value="0">
                <input type="checkbox" name="publish_now" value="1" class="rounded border-slate-300 text-brand-600">
                <span>Publish immediately <span class="block text-[11px] font-normal text-slate-400">Off = saved as a draft</span></span>
            </label>
            <label class="inline-flex items-center gap-2.5 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 dark:border-slate-600 dark:text-slate-200">
                <input type="hidden" name="notify_employees" value="0">
                <input type="checkbox" name="notify_employees" value="1" checked class="rounded border-slate-300 text-brand-600">
                <span>Notify employees <span class="block text-[11px] font-normal text-slate-400">Send a notification when published</span></span>
            </label>
            <label class="sm:col-span-2 inline-flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                <input type="hidden" name="is_pinned" value="0"><input type="checkbox" name="is_pinned" value="1" class="rounded border-slate-300 text-brand-600"> Pin to the employee dashboard
            </label>

            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                    <i data-lucide="plus" class="h-4 w-4"></i> Add event
                </button>
            </div>
        </form>
    </div>

    {{-- ===================== EVENT DETAIL MODAL (calendar) ================= --}}
    <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="modal.open = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="px-6 py-5">
                <div class="flex items-start gap-2.5">
                    <span class="mt-1 h-3 w-3 rounded-full shrink-0" :style="`background:${modal.color}`"></span>
                    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white" x-text="modal.title"></h3>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1" x-text="modal.dates"></p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5" x-show="modal.location"><i data-lucide="map-pin" class="h-3.5 w-3.5 inline -mt-0.5"></i> <span x-text="modal.location"></span></p>
                <p class="text-sm text-slate-600 dark:text-slate-300 mt-3 whitespace-pre-line" x-show="modal.description" x-text="modal.description"></p>

                <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-900/50">
                    <template x-if="modal.published">
                        <div class="flex items-center gap-2 font-bold text-emerald-600 dark:text-emerald-400"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Published <span class="font-normal text-slate-400" x-show="modal.published_at" x-text="'· ' + modal.published_at"></span></div>
                    </template>
                    <template x-if="!modal.published">
                        <div class="flex items-center gap-2 font-bold text-slate-500"><span class="h-2 w-2 rounded-full bg-slate-400"></span> Draft — hidden from employees</div>
                    </template>
                    <div class="text-slate-400 mt-1 capitalize" x-text="'Visible to: ' + (modal.visibility === 'all' ? 'everyone' : modal.visibility)"></div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button type="button" x-show="!modal.published" @click="publish(modal.id)" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700"><i data-lucide="send" class="h-4 w-4"></i> Publish</button>
                    <button type="button" x-show="modal.published" @click="unpublish(modal.id)" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-50 px-4 py-2.5 text-sm font-bold text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-400"><i data-lucide="eye-off" class="h-4 w-4"></i> Hide</button>
                    <button type="button" @click="pin(modal.id)" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200"><i data-lucide="pin" class="h-4 w-4"></i> <span x-text="modal.pinned ? 'Unpin' : 'Pin'"></span></button>
                    <button type="button" @click="editFromCalendar(modal.id)" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200"><i data-lucide="pencil" class="h-4 w-4"></i> Edit</button>
                    <form :action="`/events/${modal.id}`" method="POST" class="col-span-2" onsubmit="return confirm('Delete this event?');">@csrf @method('DELETE')<button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-700 hover:bg-rose-100 dark:bg-rose-500/10 dark:text-rose-400"><i data-lucide="trash-2" class="h-4 w-4"></i> Delete</button></form>
                </div>
            </div>
            <button type="button" @click="modal.open=false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
    </div>

    {{-- Day chooser (a date with more than one event) --}}
    <div x-show="day.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="day.open = false"></div>
        <div class="relative w-full max-w-sm rounded-2xl bg-white shadow-xl dark:bg-slate-800">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white" x-text="day.label"></h3>
                <button type="button" @click="day.open = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="p-2 max-h-[60vh] overflow-y-auto">
                <template x-for="ev in day.events" :key="ev.id">
                    <button type="button" @click="openFromDay(ev.id)" class="w-full flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700/40">
                        <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="`background:${ev.color}`"></span>
                        <span class="flex-1 text-sm font-bold text-slate-800 dark:text-white truncate" x-text="ev.title"></span>
                        <span x-show="!ev.published" class="text-[9px] font-bold text-slate-400 border border-dashed border-slate-300 rounded px-1.5 py-0.5">Draft</span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[60] rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg dark:bg-slate-700">
        <span x-text="toast"></span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
    const EV_CSRF = document.querySelector('meta[name=csrf-token]').content;
    async function evPost(url) {
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': EV_CSRF, 'Accept': 'application/json' } });
            return res.ok ? await res.json() : null;
        } catch (e) { return null; }
    }
    function evToast(msg) { window.dispatchEvent(new CustomEvent('toast', { detail: msg })); }
    function evRefreshIcons() { window.lucide && window.lucide.createIcons(); }

    // Per-list-row state + actions.
    function eventRow(init) {
        return {
            id: init.id, published: init.published, pinned: init.pinned, editOpen: false,
            async togglePublish() {
                const d = await evPost(`/events/${this.id}/${this.published ? 'unpublish' : 'publish'}`);
                if (d && d.success) { this.published = !this.published; evToast(d.message); window.dispatchEvent(new CustomEvent('event-updated', { detail: { id: this.id, published: this.published, pinned: this.pinned } })); this.$nextTick(evRefreshIcons); }
            },
            async togglePin() {
                const d = await evPost(`/events/${this.id}/toggle-pin`);
                if (d && d.success) { this.pinned = d.pinned; evToast(d.pinned ? 'Pinned to dashboard' : 'Unpinned'); window.dispatchEvent(new CustomEvent('event-updated', { detail: { id: this.id, published: this.published, pinned: this.pinned } })); this.$nextTick(evRefreshIcons); }
            },
        };
    }

    // Page-level: tabs, calendar, detail modal.
    function eventsPage() {
        return {
            tab: (new URLSearchParams(location.search)).get('tab') || 'calendar', calendar: null, toast: '',
            modal: { open: false, id: null, title: '', dates: '', location: '', description: '', published: false, pinned: false, visibility: 'all', published_at: null, color: '#F5C800' },
            day: { open: false, label: '', events: [] },
            init() {
                this.$nextTick(() => this.mountCalendar());
                this.$watch('tab', v => { if (v === 'calendar' && this.calendar) this.$nextTick(() => this.calendar.updateSize()); });
            },
            mountCalendar() {
                const el = document.getElementById('admin-calendar');
                if (!el || this.calendar || typeof FullCalendar === 'undefined') return;
                this.calendar = new FullCalendar.Calendar(el, {
                    initialView: 'dayGridMonth', height: 'auto', firstDay: 1,
                    events: '{{ route('events.calendar-data') }}',
                    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek,listMonth' },
                    eventDidMount: (info) => {
                        const p = info.event.extendedProps;
                        if (!p.is_published) {
                            info.el.style.opacity = '0.55'; info.el.style.borderStyle = 'dashed';
                            const t = info.el.querySelector('.fc-event-title');
                            if (t) { const b = document.createElement('span'); b.className = 'draft-badge'; b.textContent = 'Draft'; t.appendChild(b); }
                        }
                        if (p.is_pinned) { const t = info.el.querySelector('.fc-event-title'); if (t) t.insertAdjacentText('afterbegin', '📌 '); }
                    },
                    eventClick: (info) => { info.jsEvent.preventDefault(); this.openModal(info.event); },
                    dateClick: (info) => { this.openDay(info.dateStr); },
                });
                this.calendar.render();
            },
            // Clicking a day cell: 1 event on it → open its detail; several → a
            // chooser; none → jump to Add with the date pre-filled.
            openDay(dateStr) {
                if (!this.calendar) return;
                const onDay = this.calendar.getEvents().filter(ev => {
                    const s = ev.startStr.slice(0, 10);
                    const eEx = ev.endStr ? ev.endStr.slice(0, 10) : null;
                    return eEx ? (dateStr >= s && dateStr < eEx) : (dateStr === s);
                });
                if (onDay.length === 1) { this.openModal(onDay[0]); return; }
                if (onDay.length === 0) {
                    this.tab = 'add';
                    this.$nextTick(() => { const d = document.getElementById('add-event-date'); if (d) { d.value = dateStr; d.focus(); } });
                    return;
                }
                this.day = {
                    open: true,
                    label: new Date(dateStr).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
                    events: onDay.map(ev => ({ id: ev.id, title: ev.title, color: ev.backgroundColor || '#F5C800', published: !!ev.extendedProps.is_published })),
                };
                this.$nextTick(evRefreshIcons);
            },
            openFromDay(id) { this.day.open = false; const fc = this.calendar.getEventById(id); if (fc) this.openModal(fc); },
            openModal(ev) {
                const p = ev.extendedProps;
                const fmt = d => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                let dates = ev.start ? fmt(ev.start) : '';
                if (p.end_display) { const ed = fmt(p.end_display); if (ed !== dates) dates += ' – ' + ed; }
                this.modal = { open: true, id: ev.id, title: ev.title, dates, location: p.location || '', description: p.description || '', published: !!p.is_published, pinned: !!p.is_pinned, visibility: p.visibility || 'all', published_at: p.published_at, color: ev.backgroundColor || '#F5C800' };
                this.$nextTick(evRefreshIcons);
            },
            async publish(id) { const d = await evPost(`/events/${id}/publish`); if (d && d.success) { this.modal.published = true; this.modal.published_at = d.published_at; this.afterChange(id, true, this.modal.pinned, d.message); } },
            async unpublish(id) { const d = await evPost(`/events/${id}/unpublish`); if (d && d.success) { this.modal.published = false; this.modal.published_at = null; this.afterChange(id, false, this.modal.pinned, d.message); } },
            async pin(id) { const d = await evPost(`/events/${id}/toggle-pin`); if (d && d.success) { this.modal.pinned = d.pinned; this.afterChange(id, this.modal.published, d.pinned, d.pinned ? 'Pinned' : 'Unpinned'); } },
            afterChange(id, published, pinned, msg) {
                evToast(msg);
                if (this.calendar) this.calendar.refetchEvents();
                window.dispatchEvent(new CustomEvent('event-updated', { detail: { id, published, pinned } }));
                this.$nextTick(evRefreshIcons);
            },
            onEventUpdated(detail) { if (this.calendar) this.calendar.refetchEvents(); },
            // Open the event's edit popup straight from the calendar (reuses the
            // row's server-prefilled edit modal, which is teleported to <body>).
            editFromCalendar(id) {
                this.modal.open = false;
                window.dispatchEvent(new CustomEvent('open-event-edit', { detail: { id } }));
                this.$nextTick(evRefreshIcons);
            },
        };
    }
</script>
@endsection
