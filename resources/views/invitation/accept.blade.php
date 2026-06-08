<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accept Invitation - {{ config('app.name', 'Laravel') }}</title>
    
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
                            500: '#fcd82f',
                            600: '#eab308',
                            700: '#ca8a04',
                            800: '#a16207',
                            900: '#713f12',
                            950: '#422006',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased font-sans flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full p-6">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-brand-600">{{ config('app.name', 'Workable') }}</h1>
            <p class="text-slate-500 mt-2">Welcome aboard, {{ $employee->first_name }}!</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6">
                <h2 class="text-xl font-bold text-slate-800 mb-2">Set up your account</h2>
                <p class="text-slate-500 text-sm mb-6">Create a password to activate your account and log in.</p>

                @if($errors->has('token'))
                    <div class="mb-4 bg-red-50 text-red-800 p-4 rounded-lg text-sm flex items-start">
                        <svg class="w-5 h-5 text-red-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ $errors->first('token') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('invitation.submit', $token) }}">
                    @csrf

                    <div class="space-y-4">
                        <!-- Email (Disabled) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" value="{{ $employee->email }}" disabled
                                class="w-full rounded-lg border-slate-300 bg-slate-100 shadow-sm text-slate-500 cursor-not-allowed">
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                            <input id="password" type="password" name="password" required autofocus
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 @error('password') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                            @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-slate-500 mt-1">Must be at least 8 characters with letters, numbers, and symbols.</p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                            Activate Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
