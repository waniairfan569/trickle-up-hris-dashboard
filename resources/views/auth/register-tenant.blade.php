<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create your workspace · Trickle Hub</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                colors: {
                    brand: { 400:'#fce368', 500:'#fcd82f', 600:'#eab308', 700:'#ca8a04' },
                    slate: { 800:'#21212e', 850:'#1f1f2b', 900:'#1a1a24', 950:'#0a0a0f' }
                }
            } }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    @if($turnstileSiteKey ?? null)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
    <style>.glow-effect{box-shadow:0 0 50px -10px rgba(84,94,255,0.2)}.backdrop-blur-xl{backdrop-filter:blur(24px)}</style>
</head>
<body class="min-h-full font-sans text-slate-100 flex flex-col justify-center items-center overflow-x-hidden relative bg-slate-950 px-6 py-10">

    <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-brand-500/10 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-brand-600/10 blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md space-y-7 z-10">

        <div class="flex flex-col items-center text-center space-y-3">
            <a href="/" class="flex items-center gap-x-2.5 group">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-transparent group-hover:scale-105 transition duration-300">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-9 w-9 object-contain">
                </div>
                <div class="text-left">
                    <span class="block text-base font-extrabold tracking-tight text-white">Trickle Hub</span>
                    <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">HR for agencies</span>
                </div>
            </a>
            <h2 class="text-2xl font-extrabold text-white tracking-tight">Create your workspace</h2>
            <p class="text-xs text-slate-400 max-w-xs leading-relaxed">Set up your agency in a minute — start a free 14-day trial. No card required.</p>
        </div>

        <div class="glow-effect p-8 rounded-3xl bg-slate-900/40 border border-slate-800 backdrop-blur-xl space-y-5">

            @if($errors->any())
                <div class="rounded-xl bg-rose-500/10 border border-rose-500/20 p-3 text-xs text-rose-300">
                    <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.post') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Agency / company name</label>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required autofocus placeholder="Acme Agency"
                           class="block w-full text-sm border border-slate-800 rounded-xl px-3.5 py-3 bg-slate-950/60 text-white placeholder-slate-600 focus:border-brand-500 focus:outline-none transition">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">First name</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required placeholder="Jane"
                               class="block w-full text-sm border border-slate-800 rounded-xl px-3.5 py-3 bg-slate-950/60 text-white placeholder-slate-600 focus:border-brand-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Last name</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required placeholder="Doe"
                               class="block w-full text-sm border border-slate-800 rounded-xl px-3.5 py-3 bg-slate-950/60 text-white placeholder-slate-600 focus:border-brand-500 focus:outline-none transition">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Work email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="you@agency.com"
                           class="block w-full text-sm border border-slate-800 rounded-xl px-3.5 py-3 bg-slate-950/60 text-white placeholder-slate-600 focus:border-brand-500 focus:outline-none transition">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="block w-full text-sm border border-slate-800 rounded-xl px-3.5 py-3 bg-slate-950/60 text-white placeholder-slate-600 focus:border-brand-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Confirm</label>
                        <input type="password" name="password_confirmation" required placeholder="••••••••"
                               class="block w-full text-sm border border-slate-800 rounded-xl px-3.5 py-3 bg-slate-950/60 text-white placeholder-slate-600 focus:border-brand-500 focus:outline-none transition">
                    </div>
                </div>
                <label class="flex items-start gap-2.5 text-xs text-slate-400 cursor-pointer">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms')) required
                           class="mt-0.5 h-4 w-4 rounded border-slate-700 bg-slate-950 text-brand-500 focus:ring-brand-500">
                    <span>I agree to the
                        <a href="{{ route('legal.terms') }}" target="_blank" class="text-brand-400 hover:underline">Terms of Service</a> and
                        <a href="{{ route('legal.privacy') }}" target="_blank" class="text-brand-400 hover:underline">Privacy Policy</a>.
                    </span>
                </label>
                @error('terms')<p class="text-xs font-semibold text-rose-400">{{ $message }}</p>@enderror
                @if($turnstileSiteKey ?? null)
                    <div class="cf-turnstile mt-1" data-sitekey="{{ $turnstileSiteKey }}" data-theme="dark"></div>
                @endif
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-3 text-sm font-extrabold text-slate-900 hover:bg-brand-400 transition">
                    <i data-lucide="rocket" class="h-4 w-4"></i> Create workspace
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-500">
            Already have a workspace?
            <a href="{{ route('login') }}" class="font-bold text-brand-400 hover:text-brand-300">Sign in</a>
        </p>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
