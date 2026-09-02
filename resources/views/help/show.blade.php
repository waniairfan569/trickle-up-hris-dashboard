@extends('layouts.help')
@section('help-title', $article['title'])
@section('help-body')
<div class="mb-6">
    <a href="{{ route('help.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800">
        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> All articles
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-[1fr,240px] gap-8">
    <article class="rounded-2xl border border-slate-200 bg-white shadow-sm p-7 sm:p-9">
        <p class="text-xs font-bold uppercase tracking-wider text-indigo-500">{{ $categories[$article['category']]['label'] ?? 'Help' }}</p>
        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900">{{ $article['title'] }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $article['summary'] }}</p>
        <div class="prose mt-6 border-t border-slate-100 pt-6">
            {!! $article['html'] !!}
        </div>
    </article>

    <aside class="lg:pt-2">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">More articles</p>
        <nav class="space-y-1">
            @foreach($grouped as $cat)
                @foreach($cat['articles'] as $a)
                    <a href="{{ route('help.show', $a['slug']) }}"
                       class="block rounded-lg px-3 py-2 text-sm font-medium transition {{ $a['slug'] === $article['slug'] ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $a['title'] }}
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>
</div>

<div class="max-w-3xl mt-8 rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm">
    <p class="text-sm text-slate-600">Still need help? <a href="{{ url('/login') }}" class="font-bold text-indigo-600 hover:underline">Sign in</a> and reach out from inside the app, or <a href="mailto:{{ config('legal.contact_email') }}" class="font-bold text-indigo-600 hover:underline">email us</a>.</p>
</div>
@endsection
