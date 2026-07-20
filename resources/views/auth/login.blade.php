<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trickle Hub - Administrative Portal Access</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (via Play CDN) -->
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
            box-shadow: 0 0 50px -10px rgba(84, 94, 255, 0.2);
        }
        .backdrop-blur-xl {
            backdrop-filter: blur(24px);
        }
    </style>
</head>
<body class="h-full font-sans text-slate-100 flex flex-col justify-center items-center overflow-x-hidden relative bg-slate-950 px-6">
    
    <!-- Radial Background Accents -->
    <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-brand-500/10 blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-brand-600/10 blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md space-y-8 z-10">
        
        <!-- Logo Header -->
        <div class="flex flex-col items-center text-center space-y-4">
            <a href="/" class="flex items-center gap-x-2.5 group">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-transparent group-hover:scale-105 transition duration-300">
                    <img src="{{ asset('images/logo.png') }}" alt="Trickle Up Logo" class="h-9 w-9 object-contain">
                </div>
                <div class="text-left">
                    <span class="block text-base font-extrabold tracking-tight text-white">Trickle Up</span>
                    <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">Workspace Portal</span>
                </div>
            </a>
            <h2 class="text-2xl font-extrabold text-white tracking-tight">Administrative Authentication</h2>
            <p class="text-xs text-slate-400 max-w-xs leading-relaxed">
                Provide credentials below or use the quick access badges for rapid workspace evaluation.
            </p>
        </div>

        <!-- Form Card -->
        <div class="glow-effect p-8 rounded-3xl bg-slate-900/40 border border-slate-800 backdrop-blur-xl space-y-6">
            
            @if($errors->any())
                <div class="rounded-2xl bg-rose-500/10 border border-rose-500/20 p-4">
                    <div class="flex">
                        <i data-lucide="alert-circle" class="h-5 w-5 text-rose-400 flex-shrink-0"></i>
                        <div class="ml-3 text-xs font-semibold text-rose-300">
                            {{ $errors->first() }}
                        </div>
                    </div>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-5">
                @csrf
                
                <!-- Email Input -->
                <div class="space-y-1.5">
                    <label for="email" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email Address</label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="mail" class="h-4.5 w-4.5"></i>
                        </div>
                        <input type="email" name="email" id="email" required placeholder="you@trickleup.co" class="block w-full text-xs font-semibold border border-slate-800 rounded-xl pl-10 pr-3.5 py-3.5 bg-slate-950/60 text-white placeholder-slate-650 focus:border-brand-500 focus:outline-none transition duration-150">
                    </div>
                </div>

                <!-- Password Input -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Password</label>
                        <a href="{{ route('password.request') }}" class="text-[10px] font-bold text-brand-500 hover:text-brand-400 uppercase tracking-wider transition">Forgot password?</a>
                    </div>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="key-round" class="h-4.5 w-4.5"></i>
                        </div>
                        <input type="password" name="password" id="password" required placeholder="Enter your password" class="block w-full text-xs font-semibold border border-slate-800 rounded-xl pl-10 pr-11 py-3.5 bg-slate-950/60 text-white placeholder-slate-650 focus:border-brand-500 focus:outline-none transition duration-150">
                        <button type="button" onclick="togglePwd()" tabindex="-1" aria-label="Show or hide password"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition">
                            <i id="eye-show" data-lucide="eye" class="h-4.5 w-4.5"></i>
                            <i id="eye-hide" data-lucide="eye-off" class="h-4.5 w-4.5 hidden"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full inline-flex justify-center items-center gap-x-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-xs font-extrabold text-slate-900 py-4 shadow-lg shadow-brand-500/20 transition duration-150">
                    <span>Authenticate Console Session</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-500">
            New agency?
            <a href="{{ route('register') }}" class="font-bold text-brand-400 hover:text-brand-300">Create your workspace</a>
        </p>

    </div>

    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
        function togglePwd() {
            var input = document.getElementById('password');
            var show = document.getElementById('eye-show');
            var hide = document.getElementById('eye-hide');
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            show.classList.toggle('hidden', reveal);
            hide.classList.toggle('hidden', !reveal);
        }
    </script>
</body>
</html>
