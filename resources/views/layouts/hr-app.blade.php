<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply the user's theme before paint (no flash). 'system' follows the OS. --}}
    <script>
        (function () {
            var t = @json(optional(auth()->user())->theme ?? 'system');
            var dark = t === 'dark' || (t === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var el = document.documentElement;
            el.classList.toggle('dark', dark);
            // Paint the base colours INLINE right now, before the Tailwind CDN has
            // generated the dark: utilities — otherwise the page flashes white
            // ("half white") until the CDN finishes. color-scheme also darkens
            // native form controls and scrollbars.
            el.style.backgroundColor = dark ? '#0f172a' : '#f8fafc';
            el.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    @php $brandName = \App\Tenancy\Brand::name(); $brandLogo = \App\Tenancy\Brand::logo(); $brandColor = \App\Tenancy\Brand::color(); @endphp
    <title>@yield('title', 'Dashboard') · {{ $brandName }}</title>
    <link rel="icon" type="image/png" href="{{ $brandLogo }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS 3.x Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#fefce8',
                            100: '#fef9c3',
                            200: '#fef08a',
                            300: '#fde047',
                            400: '{{ $brandColor ?: '#fce368' }}',
                            500: '{{ $brandColor ?: '#fcd82f' }}', // tenant accent, else career yellow
                            600: '{{ $brandColor ?: '#eab308' }}',
                            700: '{{ $brandColor ?: '#ca8a04' }}',
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

    {{-- Shared button system — ONE source of truth so every button matches.
         Pill-shaped, consistent padding, driven by the tenant brand colour.
         Use: <button class="btn-brand">…</button> (primary),
              .btn-dark (stop/negative, e.g. Clock out), .btn-success (Clock in),
              .btn-outline (secondary), .btn-danger (destructive).
         Add .btn-sm / .btn-block for size/width variants. --}}
    <style type="text/tailwindcss">
        @layer components {
            .btn {
                @apply inline-flex items-center justify-center gap-2 rounded-full px-4 py-2.5 text-sm font-bold leading-none transition disabled:opacity-60 disabled:cursor-not-allowed;
            }
            .btn-sm    { @apply px-3 py-2 text-xs gap-1.5; }
            .btn-block { @apply w-full; }
            .btn-brand {
                @apply btn bg-gradient-to-r from-brand-400 to-brand-500 text-slate-900 shadow-sm shadow-brand-500/20 hover:from-brand-500 hover:to-brand-600;
            }
            .btn-dark {
                @apply btn bg-slate-900 text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100;
            }
            .btn-success {
                @apply btn bg-emerald-600 text-white hover:bg-emerald-700;
            }
            .btn-danger {
                @apply btn bg-rose-600 text-white hover:bg-rose-700;
            }
            .btn-outline {
                @apply btn border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700;
            }
        }
    </style>

    <!-- Alpine.js 3.x Play CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        [x-cloak] { display: none !important; }

        /* Default form-control styling — the Play CDN ships without @tailwindcss/forms,
           and its Preflight resets form-element padding to 0 (specificity 0,0,1) and
           every border-width to 0 (via *). The `html` prefix here lifts our defaults to
           specificity (0,0,2) so they reliably beat Preflight no matter the inject order,
           while the :where() around the exclusions stays weightless — so any explicit
           border-*/px-*/py- utility (0,1,0) on a field still wins. */
        html input:where(:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]):not([type=color]):not([type=submit]):not([type=button]):not([type=hidden])),
        html textarea,
        html select {
            border-width: 1px;
            border-style: solid;
            border-color: #cbd5e1; /* slate-300 */
            border-radius: 0.75rem;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.5;
            background-color: #ffffff;
            color: #0f172a;
        }
        .dark input:where(:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]):not([type=color]):not([type=submit]):not([type=button]):not([type=hidden])),
        .dark textarea,
        .dark select {
            background-color: #1a1a24; /* slate-900 */
            border-color: #334155;     /* slate-700 */
            color: #ffffff;
        }

        /* Premium custom scrollbar styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.5);
        }

        /* Hide scrollbars while keeping scroll functional (used on in-card scroll areas) */
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; width: 0; height: 0; }
    </style>
</head>
<body class="h-full font-sans text-slate-800 antialiased bg-slate-50/50 dark:bg-slate-900 dark:text-slate-100" x-data="{ sidebarOpen: false }">
    
    <div class="min-h-full flex">
        
        <!-- Mobile Sidebar Overlay (Alpine.js controlled) -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden x-cloak" 
             @click="sidebarOpen = false"></div>

        <!-- Mobile Sidebar Drawer (Alpine.js controlled) -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-900 text-slate-100 lg:hidden x-cloak">
            
            <!-- Mobile Header Logo -->
            <div class="flex h-16 shrink-0 items-center justify-between px-6 border-b border-slate-800">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-transparent">
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }} Logo" class="h-8 w-8 object-contain">
                    </div>
                    <span class="text-xl font-bold tracking-tight text-white">{{ $brandName }}</span>
                </a>
                <button type="button" @click="sidebarOpen = false" class="rounded-lg p-1 text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <!-- Mobile Nav Links -->
            <nav class="flex-1 space-y-1 px-4 py-4 overflow-y-auto">
                @include('layouts.partials.nav-links')
            </nav>

            <!-- Mobile Footer Profile -->
            @include('layouts.partials.sidebar-footer')
        </div>

        <!-- Desktop Sidebar (Fixed) -->
        <div class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 lg:z-40 bg-slate-900 text-slate-100 shadow-xl border-r border-slate-800">
            <!-- Brand Logo -->
            <div class="flex h-16 shrink-0 items-center px-6 border-b border-slate-800">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-transparent">
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }} Logo" class="h-8 w-8 object-contain">
                    </div>
                    <span class="text-lg font-bold tracking-tight text-white">{{ $brandName }}</span>
                </a>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 space-y-1 px-4 py-6 overflow-y-auto">
                @include('layouts.partials.nav-links')
            </nav>

            <!-- Footer Profile Card -->
            @include('layouts.partials.sidebar-footer')
        </div>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex flex-col lg:pl-64 min-w-0">
            
            <!-- Topbar Header -->
            <header class="flex h-16 shrink-0 items-center gap-x-4 border-b border-slate-200/80 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8 dark:bg-slate-800 dark:border-slate-800">
                
                <!-- Mobile Toggle button -->
                <button type="button" @click="sidebarOpen = true" class="-m-2.5 p-2.5 text-slate-500 hover:text-slate-800 lg:hidden focus:outline-none dark:text-slate-400 dark:hover:text-slate-200">
                    <i data-lucide="menu" class="h-6 w-6"></i>
                </button>

                <!-- Breadcrumb / Section Name (hidden when a page sets none) -->
                @php $__bc = trim($__env->yieldContent('breadcrumb')); @endphp
                <div class="flex flex-1 flex-col justify-center sm:flex-row sm:items-center sm:justify-start gap-x-2 min-w-0 leading-tight">
                    <span class="text-[11px] sm:text-sm font-medium text-slate-400 dark:text-slate-500 select-none">Workspace</span>
                    @if($__bc !== '')
                        <i data-lucide="chevron-right" class="hidden sm:block h-4 w-4 text-slate-300 dark:text-slate-600 shrink-0"></i>
                        {{-- yieldContent already HTML-escapes the section string; output raw to avoid double-encoding ">" into "&gt;". --}}
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-300 truncate">{!! $__bc !!}</span>
                    @endif
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-x-4 lg:gap-x-6">

                    <!-- Notification Indicator Dropdown (kept last so the bell sits at the far right) -->
                    <div x-data="{ open: false }" class="relative order-last">
                        <button @click="open = !open" @click.away="open = false" type="button" class="relative rounded-full p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-700">
                            <i data-lucide="bell" class="h-5 w-5"></i>
                            @if(auth()->user() && auth()->user()->unreadNotifications->count() > 0)
                            <span class="absolute top-1.5 right-1.5 flex h-3 w-3 items-center justify-center rounded-full bg-rose-500 ring-2 ring-white dark:ring-slate-800 text-[8px] font-bold text-white">
                                {{ auth()->user()->unreadNotifications->count() }}
                            </span>
                            @endif
                        </button>

                        <div x-show="open" style="display: none;"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="fixed top-16 right-3 sm:right-6 lg:right-8 mt-1 w-[calc(100vw-1.5rem)] sm:w-96 origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-slate-900/5 focus:outline-none dark:bg-slate-800 dark:ring-white/10 z-50 overflow-hidden border border-slate-200 dark:border-slate-700">
                            
                            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Notifications</h3>
                                @if(auth()->user() && auth()->user()->unreadNotifications->count() > 0)
                                    <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Mark all as read</button>
                                    </form>
                                @endif
                            </div>

                            <div class="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700/50">
                                @if(auth()->user() && auth()->user()->unreadNotifications->count() > 0)
                                    @foreach(auth()->user()->unreadNotifications as $notification)
                                    @php $isUrgent = $notification->data['urgent'] ?? false; @endphp
                                    <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition duration-150 {{ $isUrgent ? 'bg-rose-50/40 dark:bg-rose-500/5 border-l-2 border-rose-500' : '' }}">
                                        <div class="flex gap-3">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <div class="h-8 w-8 rounded-full flex items-center justify-center {{ $isUrgent ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-brand-100 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400' }}">
                                                    <i data-lucide="{{ $notification->data['icon'] ?? 'bell' }}" class="h-4 w-4"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <a href="{{ $notification->data['url'] ?? route('notifications.index') }}" class="block group" onclick="window.markNotifRead && window.markNotifRead('{{ $notification->id }}')">
                                                    <p class="text-sm font-semibold {{ $isUrgent ? 'text-rose-700 dark:text-rose-400' : 'text-slate-900 dark:text-white' }} mb-0.5 transition">
                                                        {{ $notification->data['title'] ?? 'Notification' }}
                                                    </p>
                                                    <p class="text-xs text-slate-600 dark:text-slate-400 leading-snug">
                                                        {{ $notification->data['message'] ?? '' }}
                                                    </p>
                                                    @if(!empty($notification->data['code']))
                                                        <div class="mt-1.5 inline-block rounded-lg bg-slate-900 px-3 py-1.5 font-mono text-base font-extrabold tracking-widest text-white dark:bg-slate-950">{{ $notification->data['code'] }}</div>
                                                    @endif
                                                </a>
                                                <div class="mt-2 flex items-center justify-between">
                                                    <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500">
                                                        {{ $notification->created_at->diffForHumans() }}
                                                    </p>
                                                    <form action="{{ route('notifications.mark-read', $notification->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="text-[10px] font-bold text-brand-600 hover:underline dark:text-brand-400">Mark Read</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                @else
                                    <div class="p-6 text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-3">
                                            <i data-lucide="bell-off" class="h-5 w-5 text-slate-400"></i>
                                        </div>
                                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No new notifications</p>
                                    </div>
                                @endif
                            </div>
                            <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 dark:border-slate-700 px-4 py-3 text-center text-xs font-bold text-brand-600 hover:bg-slate-50 dark:hover:bg-slate-700/50">View all notifications</a>
                        </div>
                    </div>

                    <!-- Vertical Separator -->
                    <div class="hidden lg:block lg:h-6 lg:w-px lg:bg-slate-200 dark:lg:bg-slate-700" aria-hidden="true"></div>

                    <!-- User Brief Profile -->
                    <div class="flex items-center gap-3">
                        <a href="{{ route('employees.profile', auth()->id()) }}" class="flex items-center gap-x-2 group">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->full_name }}" class="h-9 w-9 rounded-xl object-cover ring-2 ring-slate-100 dark:ring-slate-700 group-hover:ring-brand-500 transition duration-150">
                            @else
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-indigo-500 text-sm font-semibold text-white shadow-sm ring-2 ring-slate-100 dark:ring-slate-700 group-hover:ring-brand-500 transition duration-150">
                                    {{ auth()->user()->initials }}
                                </div>
                            @endif
                            <div class="flex flex-col text-left min-w-0">
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition truncate max-w-[110px] sm:max-w-[220px]">{{ auth()->user()->full_name }}</span>
                                <span class="text-[10px] font-medium text-slate-400 dark:text-slate-500 truncate max-w-[110px] sm:max-w-[220px]">{{ auth()->user()->job_title ?: \Illuminate\Support\Str::headline(optional(auth()->user()->role)->name ?? '') }}</span>
                            </div>
                        </a>
                    </div>

                </div>

            </header>

            <!-- Main Content Grid -->
            <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8">

                {{-- Unread-announcement bar + auto-popup --}}
                @include('partials.announcement-alert')

                {{-- Impersonation banner --}}
                @if(session()->has('operator_impersonator_id'))
                    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-indigo-600 p-4 text-white shadow">
                        <p class="text-sm font-bold flex items-center gap-2"><i data-lucide="eye" class="h-5 w-5"></i> Operator view — you're impersonating <b>{{ $brandName }}</b>.</p>
                        <form action="{{ route('operator.stop-impersonating') }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-xl bg-white/15 hover:bg-white/25 px-4 py-2 text-xs font-bold">Return to operator console</button>
                        </form>
                    </div>
                @endif

                {{-- Trial / subscription banner (admins) --}}
                @php $bannerTenant = app(\App\Tenancy\TenantManager::class)->get(); @endphp
                @if($bannerTenant && optional(auth()->user())->isAdmin() && ($bannerTenant->onTrial() || $bannerTenant->trialExpired()))
                    @if($bannerTenant->trialExpired())
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-rose-50 border border-rose-200 p-4 dark:bg-rose-500/10 dark:border-rose-500/20">
                            <p class="text-sm font-semibold text-rose-800 dark:text-rose-300 flex items-center gap-2"><i data-lucide="alert-triangle" class="h-5 w-5"></i> Your free trial has ended. Choose a plan to keep your workspace active.</p>
                            <a href="{{ route('billing.index') }}" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700">View plans</a>
                        </div>
                    @else
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50 border border-brand-200 p-4 dark:bg-brand-500/10 dark:border-brand-500/20">
                            <p class="text-sm font-semibold text-brand-800 dark:text-brand-300 flex items-center gap-2"><i data-lucide="clock" class="h-5 w-5"></i> {{ $bannerTenant->trialDaysLeft() }} day(s) left in your free trial.</p>
                            <a href="{{ route('billing.index') }}" class="rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-brand-700">Choose a plan</a>
                        </div>
                    @endif
                @endif

                <!-- Feedback Toast Notifications -->
                @if(session('success'))
                    <div x-data="{ show: true }" 
                         x-show="show" 
                         x-init="setTimeout(() => show = false, 5000)"
                         class="mb-6 flex items-center justify-between rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 shadow-sm dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"
                         x-transition:leave="transition ease-in duration-300 transform opacity-0 translate-y-2">
                        <div class="flex items-center gap-3">
                            <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500 dark:text-emerald-400"></i>
                            <span class="text-sm font-medium">{{ session('success') }}</span>
                        </div>
                        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         x-init="setTimeout(() => show = false, 6000)"
                         class="mb-6 flex items-center justify-between rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-800 shadow-sm dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-400"
                         x-transition:leave="transition ease-in duration-300 transform opacity-0 translate-y-2">
                        <div class="flex items-center gap-3">
                            <i data-lucide="alert-triangle" class="h-5 w-5 text-rose-500 dark:text-rose-400"></i>
                            <span class="text-sm font-medium">{{ session('error') }}</span>
                        </div>
                        <button type="button" @click="show = false" class="text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                @endif

                @if(session('warning'))
                    <div x-data="{ show: true }"
                         x-show="show"
                         class="mb-6 flex items-start justify-between gap-3 rounded-xl bg-amber-50 border border-amber-200 p-4 text-amber-800 shadow-sm dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300">
                        <div class="flex items-start gap-3">
                            <i data-lucide="alert-triangle" class="h-5 w-5 shrink-0 text-amber-500 dark:text-amber-400"></i>
                            <span class="text-sm font-medium">{{ session('warning') }}</span>
                        </div>
                        <button type="button" @click="show = false" class="text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 shrink-0">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                @endif

                @yield('content')

            </main>

        </div>

    </div>

    <!-- Initialize Lucide Icons at the end -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            lucide.createIcons();
        });
    </script>

    @auth
    <!-- Live notification pop-ups (in-app toast + browser desktop notification) -->
    <div id="notif-toasts" style="position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;"></div>
    <script>
    (function () {
        const KEY = 'notif_seen_{{ auth()->id() }}';
        const URL = '{{ route('notifications.unread-json') }}';
        function loadSeen() { try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) { return new Set(); } }
        let seen = loadSeen();
        const firstEver = !localStorage.getItem(KEY);
        function saveSeen() { try { localStorage.setItem(KEY, JSON.stringify([...seen].slice(-300))); } catch (e) {} }
        // Keep the "already popped" set in sync across tabs so the same notification
        // isn't shown twice (once per open tab). Update the moment another tab writes.
        window.addEventListener('storage', function (e) {
            if (e.key === KEY && e.newValue) { try { JSON.parse(e.newValue).forEach(function (id) { seen.add(id); }); } catch (err) {} }
        });
        function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

        // Mark a notification read on the server (used when the user clicks it).
        function markNotifRead(id) {
            if (!id) return;
            try {
                fetch('{{ url('notifications') }}/' + id + '/mark-read', {
                    method: 'POST', keepalive: true,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
            } catch (e) {}
        }
        window.markNotifRead = markNotifRead;

        function toast(n) {
            const wrap = document.getElementById('notif-toasts');
            if (!wrap) return;
            const el = document.createElement('a');
            el.href = n.url || '#';
            el.style.cssText = 'display:block;max-width:340px;background:#1a1a24;color:#fff;border-left:4px solid #fcd82f;border-radius:10px;padding:12px 14px;box-shadow:0 10px 30px rgba(0,0,0,.25);text-decoration:none;';
            el.innerHTML = '<div style="font-weight:700;font-size:13px;">' + esc(n.title) + '</div>' + (n.message ? '<div style="font-size:12px;color:#cbd5e1;margin-top:3px;">' + esc(n.message) + '</div>' : '');
            el.addEventListener('click', function () { markNotifRead(n.id); });
            wrap.appendChild(el);
            setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 7000);
        }
        function desktop(n) {
            if (('Notification' in window) && Notification.permission === 'granted') {
                try { const dn = new Notification(n.title, { body: n.message || '' }); dn.onclick = function () { window.focus(); markNotifRead(n.id); if (n.url) location.href = n.url; }; } catch (e) {}
            }
        }
        function handle(items) {
            // Re-read the shared set first: if another tab already popped this one, skip it here.
            loadSeen().forEach(function (id) { seen.add(id); });
            (items || []).forEach(function (n) {
                if (!seen.has(n.id)) {
                    seen.add(n.id);
                    // One pop-up only: in-app toast when the tab is focused,
                    // otherwise a desktop notification — never both.
                    if (!firstEver) { if (document.hidden) { desktop(n); } else { toast(n); } }
                }
            });
            saveSeen();
        }
        function poll() {
            fetch(URL, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) { handle(d.items); })
                .catch(function () {});
        }
        // Ask for desktop-notification permission on the first click (browser gesture requirement).
        document.addEventListener('click', function () {
            if (('Notification' in window) && Notification.permission === 'default') { Notification.requestPermission().catch(function () {}); }
        }, { once: true });

        // First load: seed the "seen" set silently so we don't blast existing unread.
        if (firstEver) { poll(); localStorage.setItem(KEY, '[]'); }
        else { setTimeout(poll, 2000); }
        setInterval(poll, 20000);
        // Poll right away when the tab regains focus, for a snappy pop-up.
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
    })();
    </script>
    @endauth
</body>
</html>
