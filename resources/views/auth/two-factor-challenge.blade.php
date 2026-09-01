<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100 dark:bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function(){var d=window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark',d);
        document.documentElement.style.backgroundColor=d?'#020617':'#f1f5f9';})();
    </script>
    <title>Two-factor verification · Trickle Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Plus Jakarta Sans','sans-serif']}}}}</script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-full font-sans antialiased text-slate-800 dark:text-slate-100 grid place-items-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="mx-auto h-12 w-12 grid place-items-center rounded-2xl bg-indigo-600 text-white"><i data-lucide="shield-check" class="h-6 w-6"></i></div>
            <h1 class="mt-3 text-xl font-extrabold text-slate-900 dark:text-white">Two-factor verification</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Enter the 6-digit code from your authenticator app.</p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            @if($errors->any())
                <div class="mb-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 border border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20">{{ $errors->first() }}</div>
            @endif
            <form action="{{ route('two-factor.verify') }}" method="POST" class="space-y-4">@csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Authentication code</label>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus placeholder="123456"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-center text-lg tracking-[0.3em] font-mono dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <p class="text-[11px] text-slate-400 mt-1.5">Lost your device? Enter one of your recovery codes instead.</p>
                </div>
                <button type="submit" class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Verify</button>
            </form>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('login') }}" class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">← Back to login</a>
        </div>
    </div>
    <script>window.lucide && lucide.createIcons();</script>
</body>
</html>
