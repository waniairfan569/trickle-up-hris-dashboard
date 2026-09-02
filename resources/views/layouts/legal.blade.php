<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('doc-title') · {{ config('legal.company') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } } };</script>
    <style>
        .prose h2{font-size:1.05rem;font-weight:800;margin:1.75rem 0 .5rem;color:#0f172a}
        .prose h3{font-size:.95rem;font-weight:700;margin:1.25rem 0 .35rem;color:#1e293b}
        .prose p,.prose li{font-size:.9rem;line-height:1.7;color:#334155;margin:.4rem 0}
        .prose ul{list-style:disc;padding-left:1.25rem;margin:.4rem 0}
        .prose a{color:#4f46e5;text-decoration:underline}
    </style>
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800">
    <div class="max-w-3xl mx-auto px-5 py-10">
        <div class="flex items-center justify-between mb-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-700 hover:text-slate-900">
                <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" class="h-7 w-7 rounded-lg" alt="">
                {{ config('legal.company') }}
            </a>
            <nav class="flex items-center gap-4 text-xs font-semibold text-slate-500">
                <a href="{{ route('legal.terms') }}" class="hover:text-slate-800">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-slate-800">Privacy</a>
                <a href="{{ route('legal.dpa') }}" class="hover:text-slate-800">DPA</a>
                <a href="{{ route('status') }}" class="hover:text-slate-800">Status</a>
            </nav>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-7 sm:p-10">
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">@yield('doc-title')</h1>
            <p class="mt-1 text-xs font-semibold text-slate-400">Version {{ config('legal.version') }} · {{ config('legal.legal_entity') }}</p>

            @if(config('legal.is_template'))
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                    <b>Template.</b> This is a starting-point document. Have it reviewed by qualified legal counsel and set <code>LEGAL_IS_TEMPLATE=false</code> before relying on it.
                </div>
            @endif

            <div class="prose mt-6">
                @yield('doc-body')
            </div>

            <p class="mt-8 text-xs text-slate-400">Questions? Contact <a href="mailto:{{ config('legal.contact_email') }}" class="text-indigo-600">{{ config('legal.contact_email') }}</a>.</p>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">© {{ date('Y') }} {{ config('legal.legal_entity') }}. All rights reserved.</p>
    </div>
</body>
</html>
