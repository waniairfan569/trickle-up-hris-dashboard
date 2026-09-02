@extends('layouts.help')
@section('help-title', 'Help Center')
@section('help-body')
<div class="text-center max-w-xl mx-auto mb-10">
    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">How can we help?</h1>
    <p class="mt-2 text-sm text-slate-500">Guides for getting the most out of {{ config('legal.company', 'Trickle Hub') }}.</p>
    <div class="mt-5 relative">
        <i data-lucide="search" class="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input id="helpSearch" type="text" placeholder="Search the help centre…" autocomplete="off" aria-label="Search the help centre"
               class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3 py-3 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-indigo-500">
    </div>
</div>

<div id="helpEmpty" class="hidden text-center text-sm text-slate-400 py-10">No articles match your search.</div>

<div class="space-y-8">
    @foreach($grouped as $cat)
        <section class="help-category">
            <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-400 mb-3">
                <i data-lucide="{{ $cat['meta']['icon'] }}" class="h-4 w-4"></i> {{ $cat['meta']['label'] }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($cat['articles'] as $a)
                    <a href="{{ route('help.show', $a['slug']) }}"
                       class="help-article block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md"
                       data-text="{{ strtolower($a['title'] . ' ' . $a['summary']) }}">
                        <p class="text-sm font-bold text-slate-800">{{ $a['title'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $a['summary'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<script>
    (function () {
        var input = document.getElementById('helpSearch');
        var empty = document.getElementById('helpEmpty');
        if (!input) return;
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            var anyVisible = false;
            document.querySelectorAll('.help-category').forEach(function (cat) {
                var catVisible = false;
                cat.querySelectorAll('.help-article').forEach(function (card) {
                    var match = !q || (card.getAttribute('data-text') || '').indexOf(q) !== -1;
                    card.hidden = !match;
                    if (match) { catVisible = true; anyVisible = true; }
                });
                cat.hidden = !catVisible;
            });
            empty.hidden = anyVisible;
        });
    })();
</script>
@endsection
