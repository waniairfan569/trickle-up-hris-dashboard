@extends('layouts.hr-app')

@section('title', 'Events')
@section('breadcrumb', 'Events')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Events</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Company events shown to everyone on their dates in the dashboard.</p>
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

    <!-- Add event -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-4">Add an event</h2>
        <form action="{{ route('events.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Title <span class="text-rose-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="120" placeholder="e.g. Town Hall Meeting" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Date <span class="text-rose-500">*</span></label>
                <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
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
            <div class="sm:col-span-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
                    <i data-lucide="plus" class="h-4 w-4"></i> Add event
                </button>
            </div>
        </form>
    </div>

    <!-- Active events -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-700/60"><span class="text-sm font-bold text-slate-700 dark:text-slate-200">Upcoming & current events</span></div>
        @forelse($events as $event)
            <div class="flex items-center gap-4 px-6 py-4 border-b border-slate-100 last:border-0 dark:border-slate-700/60" x-data="{ edit: false }">
                <span class="flex h-11 w-11 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-500/10">
                    <span class="text-[10px] font-bold uppercase leading-none">{{ $event->date->format('M') }}</span>
                    <span class="text-base font-extrabold leading-none">{{ $event->date->format('d') }}</span>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $event->title }}</p>
                    <p class="text-xs text-slate-400 truncate">
                        {{ $event->date->format('d M Y') }}@if($event->is_multi_day) – {{ $event->end_date->format('d M Y') }}@endif
                        @if($event->location) · {{ $event->location }} @endif
                    </p>
                </div>
                <button @click="edit = !edit" type="button" class="text-slate-400 hover:text-slate-700" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                <form action="{{ route('events.archive', $event) }}" method="POST">@csrf<button type="submit" title="Archive" class="text-slate-400 hover:text-amber-600"><i data-lucide="archive" class="h-4 w-4"></i></button></form>
                <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Delete “{{ $event->title }}”?');">@csrf @method('DELETE')<button type="submit" title="Delete" class="text-slate-400 hover:text-rose-500"><i data-lucide="trash-2" class="h-4 w-4"></i></button></form>

                <!-- Edit modal -->
                <div x-show="edit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/50" @click="edit = false"></div>
                    <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl dark:bg-slate-800">
                        <form action="{{ route('events.update', $event) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                                <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Edit event</h2>
                                <button type="button" @click="edit = false" class="text-slate-400 hover:text-slate-700"><i data-lucide="x" class="h-5 w-5"></i></button>
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
                            </div>
                            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                                <button type="button" @click="edit = false" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</button>
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="check" class="h-4 w-4"></i> Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-14 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="calendar-heart" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No events yet</p>
                <p class="text-xs text-slate-400 mt-1">Add an event above — it'll show on the dashboard on its date.</p>
            </div>
        @endforelse
    </div>

    <!-- Archived -->
    @if($archived->count())
        <div x-data="{ open: false }" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <button @click="open = !open" type="button" class="w-full flex items-center justify-between px-6 py-4 text-left">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Archived events ({{ $archived->count() }})</span>
                <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak class="border-t border-slate-100 dark:border-slate-700/60">
                @foreach($archived as $event)
                    <div class="flex items-center gap-4 px-6 py-3.5 border-b border-slate-100 last:border-0 dark:border-slate-700/60">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-600 dark:text-slate-300 truncate">{{ $event->title }}</p>
                            <p class="text-xs text-slate-400">{{ $event->date->format('d M Y') }}</p>
                        </div>
                        <form action="{{ route('events.restore', $event) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200"><i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Restore</button></form>
                        <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Delete permanently?');">@csrf @method('DELETE')<button type="submit" title="Delete" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button></form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
