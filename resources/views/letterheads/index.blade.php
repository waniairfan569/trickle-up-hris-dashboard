@extends('layouts.hr-app')

@section('title', 'Letterheads')
@section('breadcrumb', 'Letterheads')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="file-signature" class="h-6 w-6 text-brand-500"></i> Letterheads
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Branded header &amp; footer applied to HR documents. The default is used automatically; you can override it per document.</p>
        </div>
        <a href="{{ route('letterheads.create') }}" class="btn-brand self-start"><i data-lucide="plus" class="h-4 w-4"></i> New letterhead</a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($letterheads as $lh)
            <div class="rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700 overflow-hidden">
                {{-- mini header/footer swatch --}}
                <div class="h-10 flex items-center justify-end px-4" style="background: {{ $lh->header_bg }}; border-bottom: 3px solid {{ $lh->header_accent }};">
                    <span class="text-sm font-extrabold tracking-wide" style="color:#1a1a24">{{ \Illuminate\Support\Str::limit($lh->company_name, 22) }}</span>
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-slate-800 dark:text-white">{{ $lh->name }}</h2>
                        @if($lh->is_default)<span class="rounded-full bg-brand-100 px-2 py-0.5 text-[11px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-400">Default</span>@endif
                    </div>
                    <p class="mt-1 text-xs text-slate-400 truncate">{{ $lh->address ? \Illuminate\Support\Str::of($lh->address)->replace("\n", ', ') : 'No address set' }}</p>
                    <div class="mt-3 h-6 rounded-md flex items-center justify-between px-3" style="background: {{ $lh->footer_bg }}; color: {{ $lh->footer_text }};">
                        <span class="text-[10px]">{{ $lh->email ?: '—' }}</span>
                        <span class="text-[10px]">{{ $lh->website ?: '' }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 border-t border-slate-100 dark:border-slate-700/60 px-3 py-2 text-xs">
                    <a href="{{ route('letterheads.preview', $lh) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"><i data-lucide="eye" class="h-3.5 w-3.5"></i> Preview</a>
                    <a href="{{ route('letterheads.edit', $lh) }}" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</a>
                    @unless($lh->is_default)
                        <form method="POST" action="{{ route('letterheads.default', $lh) }}">@csrf
                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"><i data-lucide="star" class="h-3.5 w-3.5"></i> Set default</button>
                        </form>
                        <form method="POST" action="{{ route('letterheads.destroy', $lh) }}" class="ml-auto" onsubmit="return confirm('Delete this letterhead? Documents using it will fall back to the default.');">@csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 font-semibold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                        </form>
                    @endunless
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
