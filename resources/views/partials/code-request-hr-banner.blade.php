@php
    $pendingCodeCount = \App\Models\CodeRequest::where('status', 'pending')->count();
@endphp
@if($pendingCodeCount > 0)
    <a href="{{ route('code-requests.pending') }}" class="flex items-center justify-between gap-3 rounded-xl bg-amber-50 border border-amber-200 px-5 py-3.5 dark:bg-amber-500/10 dark:border-amber-500/20 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/20"><i data-lucide="zap" class="h-5 w-5"></i></span>
            <div>
                <p class="text-sm font-extrabold text-amber-900 dark:text-amber-200">⚡ {{ $pendingCodeCount }} {{ \Illuminate\Support\Str::plural('employee', $pendingCodeCount) }} waiting for a login code</p>
                <p class="text-[11px] text-amber-700 dark:text-amber-400">They’re locked out of a tool — respond fast.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 text-sm font-bold text-amber-800 dark:text-amber-300">Respond now <i data-lucide="arrow-right" class="h-4 w-4"></i></span>
    </a>
@endif
