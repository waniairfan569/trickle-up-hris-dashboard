<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invalid Invitation - Trickle Hub</title>
    
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
    <div class="max-w-md w-full p-6 text-center">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-brand-600">Trickle Hub</h1>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    @if($reason === 'expired')
                        <!-- Clock icon -->
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    @else
                        <!-- Warning icon -->
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    @endif
                </svg>
            </div>
            
            <h2 class="text-2xl font-bold text-slate-900 mb-2">
                @if($reason === 'expired')
                    Invitation Expired
                @else
                    Invalid Invitation
                @endif
            </h2>
            
            <p class="text-slate-600 mb-8">
                {{ $message ?? 'This invitation link is invalid or has expired.' }}
            </p>

            <a href="{{ route('login') }}" class="inline-flex justify-center w-full py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-brand-600 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                Go to Login
            </a>
        </div>
    </div>
</body>
</html>
