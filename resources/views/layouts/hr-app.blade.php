<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Trickle Up') - Trickle Up HRIS</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    
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
                            400: '#fce368',
                            500: '#fcd82f', // exact yellow from career.trickleup.co.uk
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
    
    <!-- Alpine.js 3.x Play CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        [x-cloak] { display: none !important; }
        
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
                        <img src="{{ asset('images/logo.png') }}" alt="Trickle Up Logo" class="h-8 w-8 object-contain">
                    </div>
                    <span class="text-xl font-bold tracking-tight text-white">Trickle Up</span>
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
                        <img src="{{ asset('images/logo.png') }}" alt="Trickle Up Logo" class="h-8 w-8 object-contain">
                    </div>
                    <span class="text-lg font-bold tracking-tight text-white">Trickle Up</span>
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

                <!-- Breadcrumb / Section Name -->
                <div class="flex flex-1 items-center gap-x-2">
                    <span class="text-sm font-medium text-slate-400 dark:text-slate-500 select-none">Workspace</span>
                    <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 dark:text-slate-600"></i>
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">@yield('breadcrumb', 'Core')</span>
                </div>

                <!-- Right Header Actions -->
                <div class="flex items-center gap-x-4 lg:gap-x-6">
                    
                    <!-- Search Bar placeholder -->
                    <div class="hidden sm:block relative max-w-xs">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <i data-lucide="search" class="h-4 w-4 text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" placeholder="Search directory..." class="w-64 rounded-full border border-slate-200 bg-slate-50/50 py-1.5 pl-9 pr-4 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-brand-500">
                    </div>

                    <!-- Notification Indicator Dropdown -->
                    <div x-data="{ open: false }" class="relative">
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
                             class="absolute right-0 mt-2 w-80 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-slate-900/5 focus:outline-none dark:bg-slate-800 dark:ring-white/10 z-50 overflow-hidden border border-slate-200 dark:border-slate-700">
                            
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
                                    <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition duration-150">
                                        <div class="flex gap-3">
                                            <div class="flex-shrink-0 mt-0.5">
                                                <div class="h-8 w-8 rounded-full bg-brand-100 flex items-center justify-center dark:bg-brand-500/20 text-brand-600 dark:text-brand-400">
                                                    <i data-lucide="{{ $notification->data['icon'] ?? 'bell' }}" class="h-4 w-4"></i>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white mb-0.5">
                                                    {{ $notification->data['title'] ?? 'Notification' }}
                                                </p>
                                                <p class="text-xs text-slate-600 dark:text-slate-400 leading-snug">
                                                    {{ $notification->data['message'] ?? '' }}
                                                </p>
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
                        <a href="{{ route('employees.show', auth()->id()) }}" class="flex items-center gap-x-2 group">
                            @if(auth()->user()->avatar_url)
                                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->full_name }}" class="h-9 w-9 rounded-xl object-cover ring-2 ring-slate-100 dark:ring-slate-700 group-hover:ring-brand-500 transition duration-150">
                            @else
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-indigo-500 text-sm font-semibold text-white shadow-sm ring-2 ring-slate-100 dark:ring-slate-700 group-hover:ring-brand-500 transition duration-150">
                                    {{ auth()->user()->initials }}
                                </div>
                            @endif
                            <div class="hidden md:flex flex-col text-left">
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">{{ auth()->user()->full_name }}</span>
                                <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ auth()->user()->job_title }}</span>
                            </div>
                        </a>
                        <!-- Role Badge Component -->
                        <x-role-badge :role="auth()->user()->role" />
                    </div>

                </div>

            </header>

            <!-- Main Content Grid -->
            <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
                
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
</body>
</html>
