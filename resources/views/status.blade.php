<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>System Status · {{ config('legal.company', 'Trickle Hub') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } } };</script>
    <style>:focus-visible{outline:2px solid #4f46e5!important;outline-offset:2px!important;border-radius:6px}@media (prefers-reduced-motion:reduce){*,*::before,*::after{transition-duration:.01ms!important}}</style>
</head>
@php
    // status key -> [label, dot colour, text colour]
    $map = [
        'ok' => ['Operational', 'bg-emerald-500', 'text-emerald-600'],
        'degraded' => ['Degraded', 'bg-amber-500', 'text-amber-600'],
        'down' => ['Outage', 'bg-rose-500', 'text-rose-600'],
        'unknown' => ['Unknown', 'bg-slate-400', 'text-slate-500'],
    ];
    $banner = [
        'ok' => ['All systems operational', 'from-emerald-500 to-green-600', 'circle-check'],
        'degraded' => ['Some systems are degraded', 'from-amber-500 to-orange-600', 'triangle-alert'],
        'down' => ['We’re experiencing an outage', 'from-rose-500 to-red-600', 'octagon-alert'],
    ][$overall] ?? ['Status unknown', 'from-slate-500 to-slate-600', 'help-circle'];
@endphp
<body class="h-full bg-slate-50 font-sans text-slate-800">
    <div class="max-w-2xl mx-auto px-5 py-10">
        <div class="flex items-center justify-between mb-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-700 hover:text-slate-900">
                <img src="{{ asset('images/logo.png') }}" onerror="this.style.display='none'" class="h-7 w-7 rounded-lg" alt="">
                {{ config('legal.company', 'Trickle Hub') }}
            </a>
            <span class="text-xs font-semibold text-slate-400">System status</span>
        </div>

        {{-- Overall banner --}}
        <div class="rounded-2xl bg-gradient-to-br {{ $banner[1] }} p-6 text-white shadow-sm">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight">{{ $banner[0] }}</h1>
                    <p class="text-xs text-white/80">Last checked {{ now()->format('M j, Y · H:i') }} UTC · refreshes every 30s</p>
                </div>
            </div>
        </div>

        {{-- Subsystems --}}
        <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100">
            @foreach($systems as $sys)
                @php $m = $map[$sys['status']] ?? $map['unknown']; @endphp
                <div class="flex items-center justify-between px-5 py-4">
                    <span class="text-sm font-bold text-slate-700">{{ $sys['name'] }}</span>
                    <span class="inline-flex items-center gap-2 text-sm font-semibold {{ $m[2] }}">
                        <span class="h-2.5 w-2.5 rounded-full {{ $m[1] }}"></span> {{ $m[0] }}
                    </span>
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            Monitoring endpoint: <a href="{{ route('health') }}" class="font-mono text-slate-500 hover:text-slate-700">/health</a> ·
            <a href="{{ url('/') }}" class="hover:text-slate-700">Home</a>
        </p>
    </div>
</body>
</html>
