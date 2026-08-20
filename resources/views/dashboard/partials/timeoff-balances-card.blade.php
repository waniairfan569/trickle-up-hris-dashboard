{{-- Shared time-off balances card (same design on employee, manager & admin dashboards).
     Uses the shared $timeOffBalances and $upcomingTimeOff; computes $resetDate itself. --}}
@php
    $auth = $auth ?? auth()->user();
    // Only surface leave types the viewer is entitled to (e.g. maternity for
    // married women, paternity for married men, both after 1 year of service).
    if ($auth && isset($timeOffBalances)) {
        $timeOffBalances = $timeOffBalances->filter(fn ($b) => $b->policy && $b->policy->appliesTo($auth))->values();
    }
    $resetDate = null;
    try {
        $rd = \App\Models\LeaveYearSetting::where('is_active', true)->whereNotNull('next_renewal_date')->orderBy('next_renewal_date')->value('next_renewal_date');
        $resetDate = $rd ? \Carbon\Carbon::parse($rd) : null;
    } catch (\Throwable $e) {}
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 dark:bg-slate-800 dark:border-slate-700">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-100/50 dark:border-slate-700/50 flex items-center justify-center text-slate-400">
                <i data-lucide="calendar-check" class="h-5 w-5"></i>
            </div>
            <div>
                <h2 class="text-base font-semibold text-slate-800 dark:text-white">Your time-off balances</h2>
                @if($resetDate)<p class="text-[11px] text-slate-400">Resets {{ $resetDate->format('j F Y') }}</p>@endif
            </div>
        </div>
        <a href="{{ route('time-off.create') }}" class="btn-brand">
            <i data-lucide="calendar-plus" class="h-4 w-4"></i> Request time off
        </a>
    </div>

    @if($timeOffBalances->isEmpty())
        <p class="text-sm text-slate-500 dark:text-slate-400">No time-off balances found.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($timeOffBalances as $index => $b)
                @php
                    $total = (float) ($b->opening_balance + $b->accrued + $b->adjusted + $b->carried_over);
                    $used = (float) $b->used;
                    $remaining = max(0, $total - $used - (float) $b->pending);
                    $policyName = optional($b->policy)->name ?? 'Unpaid Leave';
                    $displayName = stripos($policyName, 'Annual') !== false ? 'Planned Leaves'
                        : (stripos($policyName, 'Casual') !== false ? 'Unplanned Leaves' : $policyName);
                    $isUnpaid = !$b->policy || !$b->policy->is_paid;
                    $unit = optional($auth->company)->leave_unit ?? 'days';
                    $pct = $total > 0 ? min(100, max(0, round($remaining / $total * 100))) : 0;
                    $bar = ['bg-cyan-400', 'bg-amber-400', 'bg-rose-400', 'bg-emerald-400', 'bg-indigo-400'][$index % 5];
                @endphp
                <div class="rounded-xl border border-slate-100/80 dark:border-slate-700/60 bg-slate-50/60 dark:bg-slate-900/40 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 truncate" title="{{ $policyName }}">{{ $displayName }}</p>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <span class="text-2xl font-extrabold text-slate-900 dark:text-white">@if($isUnpaid)&infin;@else{{ $remaining + 0 }}@endif</span>
                        @unless($isUnpaid)<span class="text-[11px] font-medium text-slate-400">of {{ $total + 0 }} {{ $unit }}</span>@endunless
                    </div>
                    @unless($isUnpaid)
                        <div class="mt-2.5 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full {{ $bar }}" style="width: {{ $pct }}%"></div>
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-400">{{ $used + 0 }} used</p>
                    @else
                        <p class="mt-2.5 text-[11px] text-slate-400">Unlimited</p>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif

    {{-- Upcoming time off — merged into the same card --}}
    @if(($upcomingTimeOff ?? collect())->isNotEmpty())
    <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-700/60">
        <h3 class="text-base font-bold text-slate-800 dark:text-white mb-3">Upcoming time off</h3>
            <div class="space-y-2.5">
                @foreach($upcomingTimeOff as $req)
                    @php
                        $uStart = \Carbon\Carbon::parse($req->start_date);
                        $uEnd = \Carbon\Carbon::parse($req->end_date);
                        $uDays = (float) ($req->days_requested ?? ($uStart->diffInDays($uEnd) + 1));
                        $uDates = $uStart->isSameDay($uEnd)
                            ? $uStart->format('D, j M')
                            : ($uStart->format('D j') . ' – ' . $uEnd->format('D j M'));
                        $uStatus = $req->status ?? 'pending';
                        $uPill = match ($uStatus) {
                            'approved' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                            'rejected', 'cancelled' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
                            default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                        };
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border border-slate-100 dark:border-slate-700 p-3">
                        <div class="h-9 w-9 shrink-0 rounded-lg bg-slate-50 dark:bg-slate-900/50 flex items-center justify-center text-slate-400"><i data-lucide="calendar-clock" class="h-4 w-4"></i></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ optional($req->policy)->name ?? 'Leave' }}</p>
                            <p class="text-[11px] text-slate-400">{{ $uDates }} · {{ $uDays + 0 }} {{ \Illuminate\Support\Str::plural('day', $uDays) }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold capitalize {{ $uPill }}">{{ $uStatus }}</span>
                    </div>
                @endforeach
            </div>
    </div>
    @endif
</div>
