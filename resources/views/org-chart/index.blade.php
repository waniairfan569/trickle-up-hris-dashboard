@extends('layouts.hr-app')

@section('title', 'Org Chart')
@section('breadcrumb', 'Org Chart')

@section('content')
<style>
    [x-cloak]{display:none!important}
    .org-tree, .org-tree ul { display:flex; justify-content:center; padding-top:24px; position:relative; }
    .org-tree li { list-style:none; position:relative; padding:24px 10px 0; text-align:center; }
    .org-tree li::before, .org-tree li::after { content:''; position:absolute; top:0; right:50%; border-top:2px solid #e2e8f0; width:50%; height:24px; }
    .org-tree li::after { right:auto; left:50%; border-left:2px solid #e2e8f0; }
    .org-tree li:only-child::after, .org-tree li:only-child::before { display:none; }
    .org-tree li:only-child { padding-top:0; }
    .org-tree li:first-child::before, .org-tree li:last-child::after { border:0 none; }
    .org-tree li:last-child::before { border-right:2px solid #e2e8f0; border-radius:0 6px 0 0; }
    .org-tree li:first-child::after { border-radius:6px 0 0 0; }
    .org-tree ul ul::before { content:''; position:absolute; top:0; left:50%; border-left:2px solid #e2e8f0; width:0; height:24px; }
    .org-card { display:inline-flex; flex-direction:column; align-items:center; min-width:160px; background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px 16px; box-shadow:0 1px 2px rgba(15,23,42,.05); }
    .org-card.hl { box-shadow:0 0 0 3px #f59e0b; }
    .org-avatar { width:48px; height:48px; border-radius:12px; object-fit:cover; }
    .org-initials { display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#f59e0b,#6366f1); color:#fff; font-weight:700; font-size:14px; }
    .org-name { font-weight:800; font-size:13px; color:#0f172a; margin-top:6px; }
    .org-title { font-size:11px; color:#64748b; }
    .org-dept { font-size:10px; font-weight:700; color:#4f46e5; background:#eef2ff; padding:1px 8px; border-radius:999px; margin-top:4px; }
    .org-toggle { margin-top:8px; font-size:10px; font-weight:700; color:#475569; background:#f1f5f9; border:none; border-radius:999px; padding:3px 10px; cursor:pointer; }
    .org-toggle:hover { background:#e2e8f0; }
</style>

<div x-data="{ zoom: 1 }">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Org Chart</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reporting hierarchy across the organisation.</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                <input type="text" id="org-search" placeholder="Search name or title…"
                       class="rounded-xl border border-slate-200 pl-8 pr-3 py-2 text-xs font-semibold focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white">
            </div>
            <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2 py-1 dark:bg-slate-800 dark:border-slate-700">
                <button type="button" @click="zoom = Math.max(0.4, Math.round((zoom-0.1)*10)/10)" class="h-7 w-7 rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 font-bold">−</button>
                <span class="text-xs font-bold text-slate-500 w-10 text-center" x-text="Math.round(zoom*100)+'%'"></span>
                <button type="button" @click="zoom = Math.min(1.5, Math.round((zoom+0.1)*10)/10)" class="h-7 w-7 rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700 font-bold">+</button>
                <button type="button" @click="zoom = 1" class="px-2 text-[10px] font-bold text-slate-500 hover:text-slate-800">Reset</button>
            </div>
            <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Directory
            </a>
        </div>
    </div>

    <!-- Chart -->
    <div class="overflow-auto rounded-2xl border border-slate-200/80 bg-slate-50/60 p-8 dark:bg-slate-900/40 dark:border-slate-700">
        @if($roots->isEmpty())
            <p class="text-sm text-slate-400 text-center py-12">No employees to display.</p>
        @else
            <div class="origin-top inline-block transition-transform" :style="'transform: scale(' + zoom + ')'">
                <ul class="org-tree">
                    @foreach($roots as $root)
                        @include('org-chart.node', ['node' => $root, 'byManager' => $byManager])
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

<script>
    (function () {
        const input = document.getElementById('org-search');
        if (!input) return;
        input.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            let first = null;
            document.querySelectorAll('.org-card').forEach(function (card) {
                const name = card.getAttribute('data-name') || '';
                const match = q !== '' && name.indexOf(q) !== -1;
                card.classList.toggle('hl', match);
                if (match && !first) first = card;
            });
            if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        });
    })();
    if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
@endsection
