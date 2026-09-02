<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing · Trickle Hub</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                colors: {
                    brand: { 50:'#fefce8',100:'#fef9c3',200:'#fef08a',300:'#fde047',400:'#fce368',500:'#fcd82f',600:'#eab308',700:'#ca8a04',800:'#a16207',900:'#713f12',950:'#422006' },
                    slate: { 800:'#21212e', 850:'#1f1f2b', 900:'#1a1a24', 950:'#0a0a0f' }
                }
            } }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .grid-bg{background-image:linear-gradient(to right,rgba(148,163,184,.06) 1px,transparent 1px),linear-gradient(to bottom,rgba(148,163,184,.06) 1px,transparent 1px);background-size:46px 46px;mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,#000 40%,transparent 100%);-webkit-mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,#000 40%,transparent 100%)}
    </style>
</head>
<body class="h-full font-sans text-slate-200 relative overflow-x-hidden">
    <div class="fixed inset-0 grid-bg pointer-events-none"></div>
    <div class="fixed top-[-15%] left-[-10%] w-[45%] h-[45%] rounded-full bg-brand-500/10 blur-[130px] pointer-events-none"></div>
    <div class="fixed bottom-[-25%] right-[-10%] w-[45%] h-[45%] rounded-full bg-indigo-600/10 blur-[130px] pointer-events-none"></div>

    <header class="relative z-30">
        <div class="max-w-6xl mx-auto px-6 py-5 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-x-2.5">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-9 w-9 object-contain">
                <span class="text-sm font-extrabold tracking-tight text-white">Trickle&nbsp;Hub</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ url('/login') }}" class="hidden sm:inline text-sm font-bold text-slate-300 hover:text-white transition">Sign in</a>
                <a href="{{ route('register') }}" class="inline-flex items-center gap-x-1.5 rounded-xl bg-brand-500 px-4 py-2 text-sm font-extrabold text-slate-950 shadow-lg shadow-brand-500/20 hover:bg-brand-400 transition">
                    Get started <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="relative z-10 max-w-6xl mx-auto px-6 pb-24">
        <section class="text-center max-w-2xl mx-auto pt-10 pb-12">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-700 bg-slate-900/60 px-3 py-1 text-xs font-bold text-brand-300">
                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i> Pricing
            </span>
            <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold tracking-tight text-white">Simple, transparent pricing</h1>
            <p class="mt-4 text-base text-slate-400">Start free, invite your team, and only pay when you’re ready. Every plan includes a free trial — no card required to start.</p>
        </section>

        @if($plans->isEmpty())
            <div class="max-w-md mx-auto rounded-2xl border border-slate-700 bg-slate-900/60 p-8 text-center">
                <p class="text-slate-300">Plans are being finalised. <a href="{{ route('register') }}" class="text-brand-400 font-bold hover:underline">Create a workspace</a> to get started on a free trial.</p>
            </div>
        @else
        <section class="grid grid-cols-1 md:grid-cols-{{ min(3, max(1, $plans->count())) }} gap-5 items-stretch">
            @foreach($plans as $i => $plan)
                @php
                    // Highlight the middle plan when there are three or more.
                    $highlight = $plans->count() >= 3 ? ($i === 1) : ($i === 0);
                    $features = $plan->features ?? [];
                    $allAccess = in_array('*', $features, true);
                    $shown = $allAccess ? array_slice(array_values($featureLabels), 0, 6) : array_map(fn ($k) => $featureLabels[$k] ?? ucfirst(str_replace('_', ' ', $k)), array_slice($features, 0, 8));
                    $extra = $allAccess ? max(0, count($featureLabels) - 6) : max(0, count($features) - 8);
                    $seats = (int) $plan->seats === 0 ? 'Unlimited employees' : 'Up to ' . $plan->seats . ' employees';
                @endphp
                <div class="relative rounded-2xl border {{ $highlight ? 'border-brand-500/60 bg-slate-900/80 shadow-2xl shadow-brand-500/10' : 'border-slate-800 bg-slate-900/50' }} p-7 flex flex-col">
                    @if($highlight)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-3 py-1 text-[11px] font-extrabold text-slate-950">Most popular</span>
                    @endif
                    <h3 class="text-lg font-extrabold text-white">{{ $plan->name }}</h3>
                    <p class="mt-1 text-xs text-slate-400 h-8">{{ $plan->blurb ?? 'Everything you need to run your team.' }}</p>
                    <div class="mt-4 flex items-end gap-1">
                        <span class="text-4xl font-extrabold text-white">{{ $symbol }}{{ rtrim(rtrim(number_format((float) $plan->price, 2), '0'), '.') }}</span>
                        <span class="mb-1 text-sm font-semibold text-slate-500">/{{ ['monthly' => 'month', 'yearly' => 'year', 'annually' => 'year'][$plan->interval] ?? ($plan->interval ?? 'month') }}</span>
                    </div>
                    <p class="mt-2 text-xs font-bold text-slate-400"><i data-lucide="users" class="inline h-3.5 w-3.5 -mt-0.5"></i> {{ $seats }}</p>
                    @if((int) $plan->trial_days > 0)
                        <p class="mt-1 text-xs font-semibold text-brand-300">{{ $plan->trial_days }}-day free trial</p>
                    @endif

                    <a href="{{ route('register') }}" class="mt-5 inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-extrabold transition {{ $highlight ? 'bg-brand-500 text-slate-950 hover:bg-brand-400' : 'bg-slate-800 text-white hover:bg-slate-700' }}">
                        Start free trial <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </a>

                    <div class="mt-6 pt-5 border-t border-slate-800 space-y-2.5">
                        @if($allAccess)
                            <p class="text-xs font-bold text-white">Everything included:</p>
                        @endif
                        @foreach($shown as $label)
                            <p class="flex items-start gap-2 text-xs text-slate-300"><i data-lucide="check" class="h-4 w-4 shrink-0 text-brand-400"></i> {{ $label }}</p>
                        @endforeach
                        @if($extra > 0)
                            <p class="text-xs font-semibold text-slate-500">+ {{ $extra }} more module{{ $extra === 1 ? '' : 's' }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
        @endif

        <section class="mt-16 text-center">
            <p class="text-sm text-slate-400">Questions about a bigger team or a custom plan? <a href="{{ route('register') }}" class="text-brand-400 font-bold hover:underline">Start a trial</a> and reach out from inside the app.</p>
        </section>
    </main>

    <footer class="relative z-10 border-t border-slate-800/60">
        <div class="max-w-6xl mx-auto px-6 py-8 flex flex-wrap items-center justify-between gap-4 text-xs text-slate-500">
            <span>© {{ date('Y') }} Trickle Hub. Prices in {{ $currency }}.</span>
            <nav class="flex items-center gap-4 font-semibold">
                <a href="{{ route('legal.terms') }}" class="hover:text-slate-300">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-slate-300">Privacy</a>
                <a href="{{ route('legal.dpa') }}" class="hover:text-slate-300">DPA</a>
                <a href="{{ route('status') }}" class="hover:text-slate-300">Status</a>
                <a href="{{ route('help.index') }}" class="hover:text-slate-300">Help</a>
                <a href="{{ url('/login') }}" class="hover:text-slate-300">Sign in</a>
            </nav>
        </div>
    </footer>

    <script>lucide.createIcons();</script>
</body>
</html>
