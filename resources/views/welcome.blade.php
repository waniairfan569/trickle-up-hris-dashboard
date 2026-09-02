<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') && config('app.name') !== 'Laravel' ? config('app.name') : 'Trickle Hub' }}</title>
    <meta name="description" content="Trickle Hub — the Trickle Up workforce platform for attendance, time off, documents and more.">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (Play CDN — no build step) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        brand: {
                            50:'#fefce8',100:'#fef9c3',200:'#fef08a',300:'#fde047',400:'#fce368',
                            500:'#fcd82f',600:'#eab308',700:'#ca8a04',800:'#a16207',900:'#713f12',950:'#422006',
                        },
                        slate: { 800:'#21212e', 850:'#1f1f2b', 900:'#1a1a24', 950:'#0a0a0f' }
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .grid-bg {
            background-image:
                linear-gradient(to right, rgba(148,163,184,.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148,163,184,.06) 1px, transparent 1px);
            background-size: 46px 46px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%);
        }
        .pulse-slow { animation: pulse 3s cubic-bezier(0.4,0,0.6,1) infinite; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.15)} }
    </style>
</head>
<body class="h-full font-sans text-slate-200 relative overflow-x-hidden">

    <!-- Background layers -->
    <div class="fixed inset-0 grid-bg pointer-events-none"></div>
    <div class="fixed top-[-15%] left-[-10%] w-[45%] h-[45%] rounded-full bg-brand-500/10 blur-[130px] pointer-events-none"></div>
    <div class="fixed bottom-[-20%] right-[-10%] w-[45%] h-[45%] rounded-full bg-indigo-600/10 blur-[130px] pointer-events-none"></div>

    <!-- ===== Header ===== -->
    <header class="relative z-30">
        <div class="max-w-6xl mx-auto px-6 py-5 flex items-center justify-between">
            <div class="flex items-center gap-x-2.5">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-9 w-9 object-contain">
                <div class="leading-tight">
                    <span class="block text-sm font-extrabold tracking-tight text-white">Trickle&nbsp;Hub</span>
                    <span class="block text-[10px] text-slate-400 font-medium uppercase tracking-[0.18em]">Trickle Up</span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('pricing') }}" class="hidden sm:inline text-sm font-bold text-slate-300 hover:text-white transition">Pricing</a>
                <a href="{{ url('/login') }}" class="inline-flex items-center gap-x-1.5 rounded-xl bg-brand-500 px-4 py-2 text-sm font-extrabold text-slate-950 shadow-lg shadow-brand-500/20 hover:bg-brand-400 transition">
                    Sign in <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- ===== Hero ===== -->
    <main class="relative z-10 flex items-center justify-center min-h-[calc(100vh-160px)]">
        <section class="max-w-2xl mx-auto px-6 text-center flex flex-col items-center">
            <img src="{{ asset('images/logo.png') }}" alt="Trickle Up" class="h-16 w-16 object-contain mb-6">

            <span class="inline-flex items-center gap-x-2 rounded-full bg-brand-500/10 px-4 py-1.5 text-xs font-bold text-brand-300 border border-brand-500/20">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-400 pulse-slow"></span>
                Trickle Up Workforce Platform
            </span>

            <h1 class="mt-6 text-4xl sm:text-5xl font-extrabold text-white tracking-tight leading-[1.1]">
                Welcome to
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 to-brand-500">Trickle&nbsp;Hub</span>
            </h1>

            <p class="mt-5 max-w-md text-base text-slate-400 leading-relaxed">
                Your workspace for attendance, time off, documents, performance and requests — all in one place.
            </p>

            <a href="{{ url('/login') }}" class="mt-9 inline-flex items-center gap-x-2 rounded-2xl bg-brand-500 px-8 py-3.5 text-sm font-extrabold text-slate-950 shadow-xl shadow-brand-500/25 hover:bg-brand-400 hover:-translate-y-0.5 transition">
                <i data-lucide="log-in" class="h-4 w-4"></i> Sign in to your account
            </a>
        </section>
    </main>

    <!-- ===== Footer ===== -->
    <footer class="relative z-10">
        <div class="max-w-6xl mx-auto px-6 py-8 flex items-center justify-center gap-x-2.5 text-slate-500">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-5 w-5 object-contain opacity-80">
            <span class="text-xs font-semibold text-slate-400">Trickle Hub</span>
            <span class="text-xs">© {{ now()->year }} Trickle Up · All rights reserved.</span>
        </div>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>
