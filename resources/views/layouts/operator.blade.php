<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100 dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (function () {
            var t = @json(optional(auth()->user())->theme ?? 'system');
            var dark = t === 'dark' || (t === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var el = document.documentElement;
            el.classList.toggle('dark', dark);
            el.style.backgroundColor = dark ? '#020617' : '#f1f5f9';
            el.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    <title>@yield('title', 'Operator') · Trickle Hub Platform</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'] } } }
        }
    </script>

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="h-full font-sans antialiased bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100" x-data="{ sidebarOpen:false }">

@php
    $rn = request()->route()?->getName() ?? '';
    $isOwner = optional(auth()->user())->isOperatorOwner();
    $navMain = array_values(array_filter([
        ['label'=>'Companies', 'icon'=>'building-2', 'route'=>'operator.index', 'active'=>$rn==='operator.index' || str_starts_with($rn,'operator.companies')],
        ['label'=>'Billing', 'icon'=>'receipt', 'route'=>'operator.billing', 'active'=>$rn==='operator.billing'],
        $isOwner ? ['label'=>'Plans', 'icon'=>'layers', 'route'=>'operator.plans', 'active'=>str_starts_with($rn,'operator.plans')] : null,
        $isOwner ? ['label'=>'Modules', 'icon'=>'layout-grid', 'route'=>'operator.modules', 'active'=>str_starts_with($rn,'operator.modules')] : null,
        $isOwner ? ['label'=>'Operators', 'icon'=>'shield', 'route'=>'operator.operators', 'active'=>str_starts_with($rn,'operator.operators')] : null,
        ['label'=>'Errors', 'icon'=>'bug', 'route'=>'operator.errors', 'active'=>str_starts_with($rn,'operator.errors')],
    ]));
    $navSoon = [];
@endphp

{{-- Off-canvas sidebar (mobile) --}}
<div x-show="sidebarOpen" x-cloak class="relative z-50 lg:hidden" @keydown.escape.window="sidebarOpen=false">
    <div class="fixed inset-0 bg-slate-900/70" @click="sidebarOpen=false"></div>
    <div class="fixed inset-y-0 left-0 w-64" x-transition:enter="transition ease-in-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0">
        @include('layouts.partials.operator-nav', ['navMain'=>$navMain, 'navSoon'=>$navSoon])
    </div>
</div>

<div class="flex h-full">
    {{-- Sidebar (desktop) --}}
    <div class="hidden lg:flex lg:flex-col lg:w-64 lg:shrink-0">
        @include('layouts.partials.operator-nav', ['navMain'=>$navMain, 'navSoon'=>$navSoon])
    </div>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="flex h-16 shrink-0 items-center gap-x-4 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8 dark:bg-slate-900 dark:border-slate-800">
            <button type="button" class="lg:hidden text-slate-500" @click="sidebarOpen=true"><i data-lucide="menu" class="h-6 w-6"></i></button>
            <div class="flex items-center gap-2 text-sm">
                <span class="font-mono text-[11px] uppercase tracking-widest text-indigo-500">Platform</span>
                <i data-lucide="chevron-right" class="hidden sm:block h-4 w-4 text-slate-300 dark:text-slate-600"></i>
                <span class="hidden sm:block font-semibold text-slate-700 dark:text-slate-200">@yield('breadcrumb', 'Operator')</span>
            </div>
            <div class="ml-auto flex items-center gap-3">
                <div class="text-right leading-tight hidden sm:block">
                    <p class="text-sm font-bold text-slate-800 dark:text-white">{{ optional(auth()->user())->full_name ?? 'Operator' }}</p>
                    <p class="text-[11px] text-indigo-500 font-semibold">Platform Owner</p>
                </div>
                <div class="h-9 w-9 grid place-items-center rounded-full bg-indigo-600 text-white text-xs font-bold">{{ optional(auth()->user())->initials ?? 'OP' }}</div>
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button title="Sign out" class="inline-grid place-items-center h-9 w-9 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800"><i data-lucide="log-out" class="h-4 w-4"></i></button>
                </form>
            </div>
        </header>

        @if(session('success'))
            <div class="mx-4 mt-4 sm:mx-6 lg:mx-8 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mx-4 mt-4 sm:mx-6 lg:mx-8 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">{{ session('error') }}</div>
        @endif

        @unless(optional(auth()->user())->hasTwoFactorEnabled() || ($rn ?? '') === 'operator.security')
            <div class="mx-4 mt-4 sm:mx-6 lg:mx-8 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800 flex items-center justify-between gap-3 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300">
                <span class="flex items-center gap-2"><i data-lucide="shield-alert" class="h-4 w-4 shrink-0"></i> Two-factor authentication isn’t set up on your operator account.</span>
                <a href="{{ route('operator.security') }}" class="shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-600">Set it up</a>
            </div>
        @endunless

        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => window.lucide && lucide.createIcons());
    document.addEventListener('alpine:initialized', () => window.lucide && lucide.createIcons());
</script>
</body>
</html>
