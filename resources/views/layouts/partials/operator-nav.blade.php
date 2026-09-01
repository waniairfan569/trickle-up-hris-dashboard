<div class="flex h-full flex-col bg-slate-900 text-slate-300">
    {{-- Brand --}}
    <div class="flex items-center gap-2.5 px-5 h-16 border-b border-slate-800 shrink-0">
        <div class="h-8 w-8 grid place-items-center rounded-lg bg-indigo-600 text-white text-sm font-extrabold">T</div>
        <div>
            <p class="text-white font-extrabold leading-none">Trickle Hub</p>
            <p class="text-[10px] font-mono uppercase tracking-widest text-indigo-400 mt-1">Platform Console</p>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <div class="px-3 mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Manage</div>
        @foreach($navMain as $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition {{ $item['active'] ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4 shrink-0"></i><span>{{ $item['label'] }}</span>
            </a>
        @endforeach

        @if(!empty($navSoon))
        <div class="px-3 mt-6 mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Soon</div>
        @endif
        @foreach($navSoon as $item)
            <div class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-500 cursor-not-allowed select-none" title="Coming soon">
                <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4 shrink-0"></i>
                <span class="flex-1">{{ $item['label'] }}</span>
                <span class="text-[9px] font-mono uppercase tracking-wide rounded bg-slate-800 px-1.5 py-0.5 text-slate-400">soon</span>
            </div>
        @endforeach
    </nav>

    {{-- Footer --}}
    <div class="px-3 py-3 border-t border-slate-800 shrink-0 space-y-1">
        @php $rn2 = request()->route()?->getName() ?? ''; @endphp
        <a href="{{ route('operator.security') }}"
           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ str_starts_with($rn2,'operator.security') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
            <i data-lucide="shield-check" class="h-4 w-4 shrink-0"></i><span class="flex-1">Security</span>
            @unless(optional(auth()->user())->hasTwoFactorEnabled())<span class="h-2 w-2 rounded-full bg-amber-400" title="2FA not set up"></span>@endunless
        </a>
        <form action="{{ route('logout') }}" method="POST" class="px-3">@csrf
            <button class="flex items-center gap-2 text-[13px] font-semibold text-slate-400 hover:text-white transition py-1">
                <i data-lucide="log-out" class="h-4 w-4"></i> Sign out
            </button>
        </form>
    </div>
</div>
