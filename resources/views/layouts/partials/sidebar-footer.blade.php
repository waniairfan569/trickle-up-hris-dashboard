<div class="mt-auto border-t border-slate-800 p-4 bg-slate-950/45">
    <div class="flex items-center justify-between">
        {{-- Avatar + name open My Profile; the sign-out icon stays a separate control --}}
        <a href="{{ route('employees.profile', auth()->id()) }}" title="My Profile"
           class="flex items-center space-x-3 min-w-0 flex-1 rounded-xl -m-1.5 p-1.5 mr-1 transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 group">
            @if(auth()->user()->avatar_url)
                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->full_name }}" class="h-9 w-9 rounded-xl object-cover ring-1 ring-slate-800 flex-shrink-0">
            @else
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-indigo-500 text-sm font-semibold text-white flex-shrink-0 shadow-inner">
                    {{ auth()->user()->initials }}
                </div>
            @endif
            <div class="flex flex-col text-left min-w-0">
                <span class="text-xs font-semibold text-white tracking-tight leading-tight truncate group-hover:text-brand-400 transition">{{ auth()->user()->full_name }}</span>
                <span class="text-[10px] text-slate-400 leading-tight truncate mt-0.5">
                    {{ auth()->user()->role->name ?? 'Employee' }}
                </span>
            </div>
        </a>
        
        <!-- Standard Logout Button/Form -->
        <a href="/logout" 
           onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();" 
           class="rounded-lg p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 transition duration-150 flex-shrink-0 focus:outline-none"
           title="Sign Out">
            <i data-lucide="log-out" class="h-4.5 w-4.5"></i>
        </a>
    </div>

    <!-- Hidden logout form -->
    <form id="logout-form-sidebar" action="{{ Route::has('logout') ? route('logout') : '/logout' }}" method="POST" class="hidden">
        @csrf
    </form>
</div>
