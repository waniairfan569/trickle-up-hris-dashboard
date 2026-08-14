@extends('layouts.hr-app')

@section('title', 'Org Chart')
@section('breadcrumb', 'Org Chart')

@section('content')
<style>
    [x-cloak]{display:none!important}
    .org-scroll { overflow:auto; background:#f4f3ee; border-radius:20px; border:1px solid #e7e5df; }
    .dark .org-scroll { background:#0f172a; border-color:#334155; }
    .org-tree, .org-tree ul { display:flex; justify-content:center; padding-top:18px; position:relative; margin:0; }
    /* Top-level roots start from the left (each subtree below stays centred under its parent). */
    .org-tree { justify-content:flex-start; }
    .org-tree li { list-style:none; position:relative; padding:18px 7px 0; display:flex; flex-direction:column; align-items:center; }
    .org-tree li::before, .org-tree li::after { content:''; position:absolute; top:0; right:50%; border-top:2px solid #c9c6bd; width:50%; height:18px; }
    .org-tree li::after { right:auto; left:50%; border-left:2px solid #c9c6bd; }
    .org-tree li:only-child::after, .org-tree li:only-child::before { display:none; }
    .org-tree li:only-child { padding-top:0; }
    .org-tree li:first-child::before, .org-tree li:last-child::after { border:0 none; }
    .org-tree li:last-child::before { border-right:2px solid #c9c6bd; border-radius:0 8px 0 0; }
    .org-tree li:first-child::after { border-radius:8px 0 0 0; }
    .org-tree ul ul::before { content:''; position:absolute; top:0; left:50%; border-left:2px solid #c9c6bd; width:0; height:18px; }
    .dark .org-tree li::before, .dark .org-tree li::after, .dark .org-tree li:last-child::before, .dark .org-tree ul ul::before { border-color:#475569; }

    .org-card { position:relative; width:214px; min-height:120px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:26px 16px 18px; box-shadow:0 1px 3px rgba(15,23,42,.07); text-align:left; }
    .org-card.is-open { border-color:#0f172a; box-shadow:0 4px 14px rgba(15,23,42,.12); }
    .org-card.hl { box-shadow:0 0 0 3px #f59e0b; }
    .dark .org-card { background:#1e293b; border-color:#334155; }
    .org-avatar, .org-initials { position:absolute; top:-22px; left:18px; width:46px; height:46px; border-radius:9999px; border:3px solid #fff; box-shadow:0 1px 3px rgba(15,23,42,.15); object-fit:cover; }
    .dark .org-avatar, .dark .org-initials { border-color:#1e293b; }
    .org-initials { display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#a5b4fc,#818cf8); color:#fff; font-weight:700; font-size:14px; }
    .org-3dot { position:absolute; top:10px; right:10px; color:#94a3b8; }
    .org-3dot:hover { color:#475569; }
    .org-name { font-weight:800; font-size:13.5px; color:#0f172a; }
    .dark .org-name { color:#fff; }
    .org-line { font-size:12px; color:#64748b; line-height:1.5; }
    .org-count { position:absolute; bottom:-13px; left:50%; transform:translateX(-50%); min-width:26px; height:26px; padding:0 8px; display:inline-flex; align-items:center; justify-content:center; border-radius:9999px; font-size:12px; font-weight:700; cursor:pointer; border:none; z-index:1; }
    .org-count.open { background:#0f172a; color:#fff; }
    .org-count.closed { background:#e2e8f0; color:#475569; }
    .org-also { margin-top:8px; padding-top:7px; border-top:1px dashed #cbd5e1; font-size:11px; color:#64748b; line-height:1.45; }
    .dark .org-also { border-color:#475569; color:#94a3b8; }
    .org-also-label { display:inline-flex; align-items:center; gap:3px; font-weight:700; color:#6366f1; text-transform:uppercase; letter-spacing:.03em; font-size:9.5px; margin-right:4px; }
    .dark .org-also-label { color:#a5b4fc; }

    /* Department group node — clusters same-department reports of a manager. */
    .org-dept { width:auto; min-width:160px; max-width:230px; padding:11px 15px; background:#fffbea; border-color:#fde047; }
    .dark .org-dept { background:#3a2e05; border-color:#a16207; }
    .org-dept-head { display:flex; align-items:center; gap:9px; }
    .org-dept-icon { flex:none; display:flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:9px; background:#fcd82f; color:#1a1a24; }
    .org-dept-name { font-weight:800; font-size:13px; color:#0f172a; white-space:nowrap; }
    .dark .org-dept-name { color:#fff; }
    .org-dept .org-line { color:#a16207; }
    .dark .org-dept .org-line { color:#fcd82f; }
</style>

<div x-data="{ zoom: 0.85 }" class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Org Chart</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Reporting hierarchy. Click a number to collapse or expand a team.</p>
        </div>
        <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer select-none whitespace-nowrap">
                <input type="checkbox" {{ $groupByDept ? 'checked' : '' }}
                       onchange="location.href='{{ url()->current() }}' + (this.checked ? '' : '?flat=1')"
                       class="rounded border-slate-300 text-brand-600 h-4 w-4 focus:ring-brand-500">
                Group by department
            </label>
            <div class="relative">
                <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                <input type="text" id="org-search" placeholder="Search name or title…"
                       class="rounded-xl border border-slate-200 pl-8 pr-3 py-2 text-xs font-semibold focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-700 dark:text-white">
            </div>
        </div>
    </div>

    <div class="relative">
        <div class="org-scroll" style="height: calc(100vh - 230px); min-height: 520px;">
            <div class="inline-block min-w-full p-12" :style="`transform: scale(${zoom}); transform-origin: top left; transition: transform .15s ease;`">
                @if(count($tree))
                    <ul class="org-tree">
                        @foreach($tree as $node)
                            @include('org-chart.node', ['node' => $node])
                        @endforeach
                    </ul>
                @else
                    <div class="text-center py-20 text-sm text-slate-400">No employees to display.</div>
                @endif
            </div>
        </div>

        <!-- Zoom controls -->
        <div class="absolute bottom-4 right-4 flex items-center gap-1 rounded-full bg-white px-2 py-1.5 shadow-lg border border-slate-200 dark:bg-slate-800 dark:border-slate-700">
            <button @click="zoom = 0.85" type="button" class="px-3 py-1 text-xs font-bold text-slate-600 hover:text-slate-900 dark:text-slate-300">Reset org chart</button>
            <button @click="zoom = Math.max(0.4, Math.round((zoom - 0.1) * 10) / 10)" type="button" class="h-7 w-7 inline-flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="minus" class="h-4 w-4"></i></button>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 min-w-[44px] text-center" x-text="Math.round(zoom * 100) + ' %'"></span>
            <button @click="zoom = Math.min(1.6, Math.round((zoom + 0.1) * 10) / 10)" type="button" class="h-7 w-7 inline-flex items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="plus" class="h-4 w-4"></i></button>
        </div>
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
                const name = (card.getAttribute('data-name') || '').toLowerCase();
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
