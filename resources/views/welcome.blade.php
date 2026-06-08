<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trickle Up HRIS Gateway</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (via Play CDN for quick visual excellence) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#fefce8',
                            100: '#fef9c3',
                            200: '#fef08a',
                            300: '#fde047',
                            400: '#fce368',
                            500: '#fcd82f',
                            600: '#eab308',
                            700: '#ca8a04',
                            800: '#a16207',
                            900: '#713f12',
                            950: '#422006',
                        },
                        slate: {
                            800: '#21212e',
                            850: '#1f1f2b',
                            900: '#1a1a24',
                            950: '#0a0a0f',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        .glow-effect {
            box-shadow: 0 0 40px -5px rgba(84, 94, 255, 0.15);
        }
        .pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .7; transform: scale(1.05); }
        }
    </style>
</head>
<body class="h-full font-sans text-slate-100 flex flex-col justify-between overflow-hidden relative">
    
    <!-- Radial Background Accents -->
    <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-brand-500/10 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-brand-600/10 blur-[120px] pointer-events-none"></div>

    <!-- Header Navbar -->
    <header class="w-full max-w-7xl mx-auto px-6 py-6 flex items-center justify-between z-10">
        <div class="flex items-center gap-x-2.5">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-transparent">
                <img src="{{ asset('images/logo.png') }}" alt="Trickle Up Logo" class="h-8 w-8 object-contain">
            </div>
            <div>
                <span class="block text-sm font-extrabold tracking-tight text-white">Trickle Up</span>
                <span class="block text-[10px] text-slate-400 font-medium uppercase tracking-wider">HRIS Platform Gateway</span>
            </div>
        </div>
        <div class="flex items-center gap-x-3">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-400 border border-emerald-500/20">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 pulse-slow"></span>
                API Backend Online
            </span>
        </div>
    </header>

    <!-- Main Content Hero Section -->
    <main class="w-full max-w-7xl mx-auto px-6 flex flex-col items-center justify-center text-center z-10 py-12 space-y-12">
        
        <!-- Welcome Title -->
        <div class="max-w-3xl space-y-4">
            <span class="inline-flex items-center gap-x-1.5 rounded-full bg-brand-500/10 px-3.5 py-1.5 text-xs font-bold text-brand-400 border border-brand-500/20">
                Enterprise Workforce Platform
            </span>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white tracking-tight leading-none">
                State-of-the-Art <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 via-indigo-400 to-purple-400">Enterprise HRIS Engine</span>
            </h1>
            <p class="text-sm sm:text-base text-slate-400 max-w-xl mx-auto leading-relaxed">
                Welcome to the Workable HR gateway portal. Choose a node below to access the employee space or administrative controls.
            </p>
        </div>

        <!-- Choice Grid -->
        <div class="flex items-center justify-center w-full">
            <a href="/dashboard" class="group glow-effect text-left flex flex-col justify-between p-8 rounded-3xl bg-slate-900/40 border border-slate-800/80 backdrop-blur-xl hover:border-indigo-500/50 hover:bg-slate-900/60 transition-all duration-300 hover:-translate-y-1.5 relative overflow-hidden">
                <!-- Hover Glow -->
                <div class="absolute inset-0 bg-gradient-to-tr from-indigo-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>

                <div class="space-y-5 relative z-10">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="settings-2" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-white group-hover:text-indigo-400 transition">Admin Dashboard Console</h3>
                        <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                            Configure custom profile templates, dynamic custom fields, security permissions, and audit logs.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-x-2 text-xs font-bold text-indigo-400 mt-8 group-hover:text-indigo-300 transition">
                    <span>Access Admin Portal</span>
                    <i data-lucide="arrow-right" class="h-4 w-4 transform group-hover:translate-x-1 transition"></i>
                </div>
            </a>

        </div>

        <!-- Health Checklist -->
        <div class="w-full max-w-4xl p-6 rounded-2xl bg-slate-900/20 border border-slate-800/40 backdrop-blur-md flex flex-col sm:flex-row items-center justify-between gap-4 text-left">
            <div class="flex items-center gap-x-3">
                <i data-lucide="shield-check" class="h-5 w-5 text-brand-400 flex-shrink-0"></i>
                <div class="text-xs">
                    <span class="block font-bold text-white">Secure by Design</span>
                    <span class="block text-slate-450 leading-normal mt-0.5">Role-based access control, dynamic employee profiles, and full audit logging.</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2.5">
                <span class="inline-flex items-center gap-x-1 rounded bg-slate-800/80 px-2 py-0.5 text-[10px] font-semibold text-slate-300">
                    &check; encrypted
                </span>
                <span class="inline-flex items-center gap-x-1 rounded bg-slate-800/80 px-2 py-0.5 text-[10px] font-semibold text-slate-300">
                    &check; rbac-secured
                </span>
                <span class="inline-flex items-center gap-x-1 rounded bg-slate-800/80 px-2 py-0.5 text-[10px] font-semibold text-slate-300">
                    &check; audit-logging
                </span>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="w-full py-6 text-center text-[10px] text-slate-500 border-t border-slate-900 z-10">
        &copy; 2026 Trickle Up HRIS. Built by Trickle Up on the Laravel framework. All rights reserved.
    </footer>

    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
