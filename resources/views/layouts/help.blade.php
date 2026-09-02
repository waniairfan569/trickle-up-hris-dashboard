<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('help-title', 'Help Center') · {{ config('legal.company', 'Trickle Hub') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } } };</script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .prose h2{font-size:1.15rem;font-weight:800;margin:1.9rem 0 .6rem;color:#0f172a}
        .prose h3{font-size:1rem;font-weight:700;margin:1.3rem 0 .4rem;color:#1e293b}
        .prose p,.prose li{font-size:.95rem;line-height:1.75;color:#334155;margin:.5rem 0}
        .prose ul{list-style:disc;padding-left:1.3rem;margin:.5rem 0}
        .prose ol{list-style:decimal;padding-left:1.3rem;margin:.5rem 0}
        .prose strong{color:#0f172a;font-weight:700}
        .prose a{color:#4f46e5;text-decoration:underline}
        :focus-visible{outline:2px solid #4f46e5!important;outline-offset:2px!important;border-radius:6px}
        .skip-link{position:absolute;left:-999px;top:0;z-index:100}
        .skip-link:focus{left:12px;top:12px;background:#4f46e5;color:#fff;padding:8px 14px;border-radius:10px;font-weight:800}
        @media (prefers-reduced-motion: reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
    </style>
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800">
    <a href="#main" class="skip-link">Skip to content</a>
    <header class="border-b border-slate-200 bg-white">
        <div class="max-w-5xl mx-auto px-5 py-4 flex items-center justify-between">
            <a href="{{ route('help.index') }}" class="inline-flex items-center gap-2 font-extrabold text-slate-800">
                <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" class="h-7 w-7 rounded-lg" alt="">
                {{ config('legal.company', 'Trickle Hub') }} <span class="text-slate-300 font-medium">Help</span>
            </a>
            <a href="{{ url('/login') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800">Sign in</a>
        </div>
    </header>

    <main id="main" class="max-w-5xl mx-auto px-5 py-10">
        @yield('help-body')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="max-w-5xl mx-auto px-5 py-6 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-400">
            <span>© {{ date('Y') }} {{ config('legal.company', 'Trickle Hub') }}</span>
            <nav class="flex items-center gap-4 font-semibold">
                <a href="{{ route('pricing') }}" class="hover:text-slate-700">Pricing</a>
                <a href="{{ route('status') }}" class="hover:text-slate-700">Status</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-slate-700">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-slate-700">Privacy</a>
            </nav>
        </div>
    </footer>
    <script>lucide.createIcons();</script>
</body>
</html>
