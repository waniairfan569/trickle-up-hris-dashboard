<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') && config('app.name') !== 'Laravel' ? config('app.name') : 'Trickle Hub' }} — HR & Workforce Platform</title>
    <meta name="description" content="The all-in-one HR & workforce platform for modern agencies — attendance, time off, documents, performance and more. Set up your branded workspace in minutes.">
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
        .card-hover { transition: transform .25s ease, border-color .25s ease, background .25s ease; }
        .card-hover:hover { transform: translateY(-4px); }
    </style>
</head>
<body class="h-full font-sans text-slate-200 relative overflow-x-hidden">

    <!-- Background layers -->
    <div class="fixed inset-0 grid-bg pointer-events-none"></div>
    <div class="fixed top-[-15%] left-[-10%] w-[45%] h-[45%] rounded-full bg-brand-500/10 blur-[130px] pointer-events-none"></div>
    <div class="fixed top-[10%] right-[-15%] w-[45%] h-[50%] rounded-full bg-indigo-600/10 blur-[130px] pointer-events-none"></div>
    <div class="fixed bottom-[-20%] left-[20%] w-[50%] h-[45%] rounded-full bg-purple-600/10 blur-[140px] pointer-events-none"></div>

    <!-- ===== Header ===== -->
    <header class="sticky top-0 z-30 backdrop-blur-xl bg-slate-950/60 border-b border-slate-800/50">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-x-2.5">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-9 w-9 object-contain">
                <div class="leading-tight">
                    <span class="block text-sm font-extrabold tracking-tight text-white">Trickle&nbsp;Hub</span>
                    <span class="block text-[10px] text-slate-400 font-medium uppercase tracking-[0.18em]">Workforce Platform</span>
                </div>
            </div>
            <nav class="flex items-center gap-x-2 sm:gap-x-3">
                <a href="{{ url('/login') }}" class="hidden sm:inline-flex items-center rounded-xl px-4 py-2 text-sm font-bold text-slate-300 hover:text-white hover:bg-slate-800/60 transition">Sign in</a>
                <a href="{{ url('/register') }}" class="inline-flex items-center gap-x-1.5 rounded-xl bg-brand-500 px-4 py-2 text-sm font-extrabold text-slate-950 shadow-lg shadow-brand-500/20 hover:bg-brand-400 transition">
                    Start free trial <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </nav>
        </div>
    </header>

    <!-- ===== Hero ===== -->
    <main class="relative z-10">
        <section class="max-w-7xl mx-auto px-6 pt-20 pb-16 sm:pt-28 text-center flex flex-col items-center">
            <span class="inline-flex items-center gap-x-2 rounded-full bg-brand-500/10 px-4 py-1.5 text-xs font-bold text-brand-300 border border-brand-500/20">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-400 pulse-slow"></span>
                The modern HR platform for growing agencies
            </span>

            <h1 class="mt-7 max-w-4xl text-4xl sm:text-6xl lg:text-7xl font-extrabold text-white tracking-tight leading-[1.05]">
                Everything your team needs,
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 via-indigo-400 to-purple-400">in one hub.</span>
            </h1>

            <p class="mt-6 max-w-2xl text-base sm:text-lg text-slate-400 leading-relaxed">
                Attendance, time off, documents, performance and requests — all in one place.
                Give your agency a fully branded workspace and run people operations without the chaos.
            </p>

            <div class="mt-9 flex flex-col sm:flex-row items-center gap-3">
                <a href="{{ url('/register') }}" class="inline-flex items-center gap-x-2 rounded-2xl bg-brand-500 px-7 py-3.5 text-sm font-extrabold text-slate-950 shadow-xl shadow-brand-500/25 hover:bg-brand-400 hover:-translate-y-0.5 transition">
                    Create your workspace <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
                <a href="{{ url('/login') }}" class="inline-flex items-center gap-x-2 rounded-2xl bg-slate-900/60 border border-slate-700/70 px-7 py-3.5 text-sm font-bold text-slate-200 hover:bg-slate-800/70 hover:border-slate-600 transition">
                    <i data-lucide="log-in" class="h-4 w-4"></i> Sign in
                </a>
            </div>

            <p class="mt-5 text-xs text-slate-500 flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                <span class="inline-flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-400"></i> 14-day free trial</span>
                <span class="inline-flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-400"></i> No credit card required</span>
                <span class="inline-flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-emerald-400"></i> White-label ready</span>
            </p>
        </section>

        <!-- ===== Feature grid ===== -->
        <section class="max-w-7xl mx-auto px-6 py-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @php
                    $features = [
                        ['clock', 'Attendance & Time Tracking', 'Biometric or dashboard clock-in, live board, grace-period late rules and a daily attendance report — always accurate.'],
                        ['palmtree', 'Time Off & Leave', 'Custom leave policies, half-day & hourly leave, approvals, and balances auto-assigned to every new hire.'],
                        ['file-signature', 'Documents & E-signatures', 'Reusable templates, in-app signing, a company document library and per-employee file storage.'],
                        ['network', 'People & Org Chart', 'Dynamic profiles, a searchable directory, multiple managers and a live reporting org chart.'],
                        ['award', 'Performance & Onboarding', 'Review cycles, probation tracking and structured onboarding workflows for new team members.'],
                        ['clipboard-list', 'Forms & Requests', 'Build custom forms and let staff raise code, equipment and correction requests — routed for approval.'],
                    ];
                @endphp
                @foreach($features as [$icon, $title, $desc])
                    <div class="card-hover group p-6 rounded-2xl bg-slate-900/40 border border-slate-800/70 backdrop-blur-xl hover:border-brand-500/40 hover:bg-slate-900/60">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-500/10 text-brand-300 border border-brand-500/20 group-hover:scale-110 transition-transform">
                            <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
                        </div>
                        <h3 class="mt-4 text-base font-bold text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-slate-400 leading-relaxed">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- ===== Security band ===== -->
        <section class="max-w-7xl mx-auto px-6 py-10">
            <div class="rounded-3xl bg-gradient-to-br from-slate-900/70 to-slate-900/30 border border-slate-800/70 backdrop-blur-xl p-8 sm:p-10">
                <div class="flex flex-col lg:flex-row lg:items-center gap-8">
                    <div class="lg:max-w-sm">
                        <span class="inline-flex items-center gap-x-2 rounded-full bg-emerald-500/10 px-3 py-1 text-[11px] font-bold text-emerald-400 border border-emerald-500/20">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Secure by design
                        </span>
                        <h2 class="mt-4 text-2xl font-extrabold text-white tracking-tight">Enterprise-grade from day one</h2>
                        <p class="mt-2 text-sm text-slate-400 leading-relaxed">Every agency gets an isolated, private workspace with role-based access and full audit trails.</p>
                    </div>
                    <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4">
                        @php
                            $trust = [
                                ['lock', 'Encrypted data'],
                                ['users-round', 'Role-based access'],
                                ['scroll-text', 'Full audit logging'],
                                ['layers', 'Tenant isolation'],
                            ];
                        @endphp
                        @foreach($trust as [$icon, $label])
                            <div class="rounded-2xl bg-slate-950/40 border border-slate-800/60 p-4 text-center">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800/60 text-brand-300">
                                    <i data-lucide="{{ $icon }}" class="h-5 w-5"></i>
                                </div>
                                <p class="mt-3 text-xs font-bold text-slate-200">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== Final CTA ===== -->
        <section class="max-w-7xl mx-auto px-6 py-12">
            <div class="relative overflow-hidden rounded-3xl border border-brand-500/20 bg-gradient-to-br from-brand-500/10 via-slate-900/40 to-indigo-600/10 p-10 sm:p-14 text-center">
                <div class="absolute inset-0 grid-bg opacity-40 pointer-events-none"></div>
                <div class="relative z-10 flex flex-col items-center">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight max-w-2xl">Give your team a home they’ll actually use.</h2>
                    <p class="mt-4 text-slate-400 max-w-xl">Spin up your branded workspace in minutes. Invite your people, set your policies, and you’re running.</p>
                    <div class="mt-8 flex flex-col sm:flex-row items-center gap-3">
                        <a href="{{ url('/register') }}" class="inline-flex items-center gap-x-2 rounded-2xl bg-brand-500 px-8 py-3.5 text-sm font-extrabold text-slate-950 shadow-xl shadow-brand-500/25 hover:bg-brand-400 hover:-translate-y-0.5 transition">
                            Start free trial <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="{{ url('/login') }}" class="inline-flex items-center gap-x-2 rounded-2xl px-6 py-3.5 text-sm font-bold text-slate-300 hover:text-white transition">
                            I already have an account
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ===== Footer ===== -->
    <footer class="relative z-10 border-t border-slate-800/50 mt-6">
        <div class="max-w-7xl mx-auto px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-x-2.5">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-6 w-6 object-contain">
                <span class="text-sm font-bold text-slate-300">Trickle Hub</span>
                <span class="text-xs text-slate-500">© {{ now()->year }} · All rights reserved.</span>
            </div>
            <div class="flex items-center gap-x-5 text-xs font-semibold text-slate-400">
                <a href="{{ url('/login') }}" class="hover:text-white transition">Sign in</a>
                <a href="{{ url('/register') }}" class="hover:text-white transition">Create workspace</a>
            </div>
        </div>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>
