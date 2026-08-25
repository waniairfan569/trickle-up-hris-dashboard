@extends('layouts.hr-app')

@section('title', 'Announcements')
@section('breadcrumb', 'Announcements')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="megaphone" class="h-6 w-6 text-brand-500"></i> Announcements
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Post updates for everyone — they appear on each employee's dashboard. Expired ones hide from employees automatically.</p>
        </div>
        <a href="{{ route('announcements.archived') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
            <i data-lucide="archive" class="h-4 w-4"></i> Archive
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <!-- New announcement -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700">
        <h2 class="text-sm font-bold text-slate-800 dark:text-white mb-3">New announcement</h2>
        <form method="POST" action="{{ route('announcements.store') }}" class="space-y-3">
            @csrf
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="200" placeholder="Title" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-semibold dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            <textarea name="body" rows="4" required maxlength="5000" placeholder="Write the announcement… you can paste links (https://…) and they'll be clickable." class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('body') }}</textarea>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="is_pinned" value="1" class="rounded border-slate-300 text-brand-600"> Pin to top
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <span class="text-slate-500 dark:text-slate-400">Expires <span class="text-slate-400">(optional)</span></span>
                        <input type="date" name="expires_at" value="{{ old('expires_at') }}" min="{{ now()->toDateString() }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </label>
                </div>
                <button type="submit" class="btn-brand"><i data-lucide="send" class="h-4 w-4"></i> Post</button>
            </div>
        </form>
    </div>

    <!-- Existing announcements -->
    <div class="space-y-3">
        @forelse($announcements as $a)
            <div class="bg-white rounded-2xl border shadow-sm p-5 dark:bg-slate-800 {{ $a->is_active ? 'border-slate-200/80 dark:border-slate-700' : 'border-slate-200 opacity-60 dark:border-slate-700' }}"
                 x-data="{ edit: false }">
                <div x-show="!edit">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($a->is_pinned)<span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-500/10"><i data-lucide="pin" class="h-3 w-3"></i> Pinned</span>@endif
                                @unless($a->is_active)<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-500 dark:bg-slate-700">Hidden</span>@endunless
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $a->title }}</h3>
                            </div>
                            <p class="text-sm text-slate-600 dark:text-slate-300 mt-1.5 whitespace-pre-line">{!! $a->bodyHtml() !!}</p>
                            <p class="text-[11px] text-slate-400 mt-2">{{ optional($a->creator)->full_name ?? 'Admin' }} · {{ $a->created_at->diffForHumans() }}</p>
                            @if($a->expires_at)
                                <p class="text-[11px] mt-1 inline-flex items-center gap-1 {{ $a->isExpired() ? 'text-rose-500 font-semibold' : 'text-slate-400' }}">
                                    <i data-lucide="clock" class="h-3 w-3"></i>{{ $a->isExpired() ? 'Expired' : 'Expires' }} {{ $a->expires_at->format('d M Y') }}
                                </p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="button" @click="edit = true" class="text-xs font-semibold text-slate-500 hover:text-slate-800">Edit</button>
                        <form method="POST" action="{{ route('announcements.toggle', $a) }}">@csrf<button type="submit" class="text-xs font-semibold text-slate-500 hover:text-slate-800">{{ $a->is_active ? 'Hide' : 'Show' }}</button></form>
                        <form method="POST" action="{{ route('announcements.destroy', $a) }}" onsubmit="return confirm('Move this announcement to the Archive? You can restore it later.')" class="ml-auto">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-rose-500 hover:text-rose-700"><i data-lucide="archive" class="h-3.5 w-3.5"></i> Archive</button></form>
                    </div>
                </div>

                <!-- Inline edit -->
                <form x-show="edit" x-cloak method="POST" action="{{ route('announcements.update', $a) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <input type="text" name="title" value="{{ $a->title }}" required maxlength="200" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm font-semibold dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <textarea name="body" rows="4" required maxlength="5000" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ $a->body }}</textarea>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" name="is_pinned" value="1" @checked($a->is_pinned) class="rounded border-slate-300 text-brand-600"> Pin to top</label>
                            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <span class="text-slate-500 dark:text-slate-400">Expires</span>
                                <input type="date" name="expires_at" value="{{ optional($a->expires_at)->toDateString() }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="edit = false" class="btn-outline">Cancel</button>
                            <button type="submit" class="btn-brand">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-14 text-center dark:bg-slate-800 dark:border-slate-700">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3 mx-auto"><i data-lucide="megaphone" class="h-7 w-7"></i></div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">No announcements yet</p>
                <p class="text-xs text-slate-400 mt-1">Post one above — it'll show on everyone's dashboard.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
