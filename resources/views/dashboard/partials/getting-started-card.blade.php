@php
    // Getting-started setup checklist — shown to admins of a new workspace until
    // every step is done or they dismiss it. Resolve the tenant the same way the
    // rest of the app does (falls back to the only tenant on a single install).
    $gsTenant = app(\App\Tenancy\TenantManager::class)->get()
        ?? (auth()->user()->tenant_id
            ? \App\Models\Tenant::find(auth()->user()->tenant_id)
            : (\App\Models\Tenant::query()->count() === 1 ? \App\Models\Tenant::query()->first() : null));

    $gsShow = $gsTenant && auth()->user()->isAdmin()
        && app(\App\Services\SetupChecklist::class)->shouldShow($gsTenant);
    $gsProgress = $gsShow ? app(\App\Services\SetupChecklist::class)->progress($gsTenant) : null;
@endphp

@if($gsShow)
<a href="{{ route('getting-started') }}"
   class="block mb-6 rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50 to-white p-5 shadow-sm transition hover:shadow-md dark:border-brand-500/25 dark:from-brand-500/10 dark:to-slate-800">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="shrink-0 grid place-items-center h-11 w-11 rounded-xl bg-brand-500 text-white"><i data-lucide="rocket" class="h-5 w-5"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-extrabold text-slate-800 dark:text-white">Finish setting up your workspace</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $gsProgress['done'] }} of {{ $gsProgress['total'] }} steps done — {{ $gsProgress['total'] - $gsProgress['done'] }} to go.</p>
            </div>
        </div>
        <span class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white">Continue <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></span>
    </div>
    <div class="mt-4 h-2 w-full rounded-full bg-brand-100 dark:bg-brand-500/20 overflow-hidden">
        <div class="h-full rounded-full bg-brand-500 transition-all duration-500" style="width: {{ $gsProgress['percent'] }}%"></div>
    </div>
</a>
@endif
