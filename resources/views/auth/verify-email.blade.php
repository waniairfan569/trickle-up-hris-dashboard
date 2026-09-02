<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm your email · Trickle Hub</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } } };
    </script>
    <style>:focus-visible{outline:2px solid #6366f1!important;outline-offset:2px!important;border-radius:6px}@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}}</style>
</head>
<body class="h-full font-sans text-slate-200 flex items-center justify-center p-4" style="background:radial-gradient(1200px 600px at 50% -10%, #1e293b, #020617);">
    <div class="w-full max-w-md">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 backdrop-blur p-8 shadow-2xl text-center">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500/15 ring-1 ring-indigo-400/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
            </div>
            <h1 class="text-xl font-extrabold text-white">Confirm your email</h1>
            <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                We’ve sent a confirmation link to
                <span class="font-semibold text-slate-200">{{ auth()->user()->email }}</span>.
                Click it to unlock your workspace. It can take a minute to arrive — check spam too.
            </p>

            @if (session('success'))
                <div class="mt-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
                @csrf
                <button type="submit" class="w-full rounded-xl bg-indigo-500 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-indigo-400">
                    Resend the link
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-slate-300">
                    Sign in with a different account
                </button>
            </form>
        </div>
        <p class="mt-6 text-center text-xs text-slate-600">
            Didn’t sign up? You can safely ignore the email.
        </p>
    </div>
</body>
</html>
