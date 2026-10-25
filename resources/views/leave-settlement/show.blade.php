@extends('layouts.hr-app')

@section('title', 'Leave settlement')
@section('breadcrumb', 'Leave settlement')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Leave settlement</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            What <span class="font-semibold">{{ $employee->full_name }}</span> has earned by their last day,
            against what they have taken.
        </p>
    </div>

    {{-- Which day to measure to. Everything else follows from this. --}}
    <form method="GET" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Last working day</label>
                <input type="date" name="date" value="{{ $leaving?->toDateString() }}"
                       class="rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                @if($recorded)
                    <p class="mt-1 text-[11px] text-slate-400">On their record: {{ \Carbon\Carbon::parse($recorded)->format('j M Y') }}</p>
                @else
                    <p class="mt-1 text-[11px] text-slate-400">No termination date on their record</p>
                @endif
            </div>
            <button class="h-10 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/50">
                Work it out
            </button>
        </div>
    </form>

    @if(! $leaving)
        <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            Pick a last working day above to see what they have earned.
        </div>
    @elseif(empty($rows))
        <div class="rounded-2xl border border-slate-200/80 bg-white p-10 text-center text-sm text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            They hold no leave balances, so there is nothing to settle.
        </div>
    @else
        @php
            $settledAlready = collect($rows)->filter(fn ($r) => $r['balance']->settled_at);
        @endphp

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700/60">
                <thead class="bg-slate-50 dark:bg-slate-700/30">
                    <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="px-5 py-3">Policy</th>
                        <th class="px-5 py-3 text-right">Full year</th>
                        <th class="px-5 py-3 text-right">Months</th>
                        <th class="px-5 py-3 text-right">Earned</th>
                        <th class="px-5 py-3 text-right">Taken</th>
                        <th class="px-5 py-3 text-right">Owed</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @foreach($rows as $r)
                        <tr>
                            <td class="px-5 py-3 text-sm font-semibold text-slate-800 dark:text-white">{{ $r['policy'] }}</td>
                            <td class="px-5 py-3 text-sm text-right text-slate-500 dark:text-slate-400">{{ $r['full_year'] }}</td>
                            <td class="px-5 py-3 text-sm text-right text-slate-500 dark:text-slate-400">{{ $r['months_served'] }}/12</td>
                            <td class="px-5 py-3 text-sm text-right font-semibold text-slate-800 dark:text-white">{{ $r['earned'] }}</td>
                            <td class="px-5 py-3 text-sm text-right text-slate-500 dark:text-slate-400">{{ $r['taken'] }}</td>
                            <td class="px-5 py-3 text-sm text-right font-bold {{ $r['difference'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $r['difference'] > 0 ? '+' : '' }}{{ $r['difference'] }}
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 dark:text-slate-400">
                                @if($r['difference'] > 0)
                                    owed {{ $r['difference'] }} day(s)
                                @elseif($r['difference'] < 0)
                                    took {{ abs($r['difference']) }} day(s) in advance
                                @else
                                    square
                                @endif
                                @if($r['balance']->settled_at)
                                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        settled {{ $r['balance']->settled_at->format('j M Y') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="text-sm text-amber-900 dark:text-amber-200">
                <strong>Writing this adjusts their balances</strong> so each year grants what they actually earned,
                and what is left showing becomes the number they are owed. Leave already taken is not touched and
                nothing is deleted.
            </p>
            <p class="mt-2 text-xs text-amber-900/80 dark:text-amber-200/80">
                Paying those days out, or reclaiming them, is still yours to do — this only makes the record agree
                with the facts.
            </p>

            <form method="POST" action="{{ route('leave-settlement.apply', $employee) }}" class="mt-4 flex flex-wrap items-center gap-3"
                  onsubmit="return confirm('Write this settlement to {{ addslashes($employee->full_name) }}\'s balances?')">
                @csrf
                <input type="hidden" name="leaving_date" value="{{ $leaving->toDateString() }}">

                @if($settledAlready->isNotEmpty())
                    <label class="flex items-center gap-2 text-xs font-semibold text-amber-900 dark:text-amber-200">
                        <input type="checkbox" name="again" value="1" class="rounded border-amber-300 text-amber-600">
                        {{ $settledAlready->count() }} of these were settled before — settle again, replacing what was written
                    </label>
                @endif

                <button class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-amber-700">
                    Write settlement
                </button>
            </form>
        </div>
    @endif

    <div class="text-xs text-slate-400">
        “Earned” is their allowance pro-rated to the months they served, plus anything carried over or
        adjusted by hand — those were earned elsewhere and are not pro-rated away.
    </div>
</div>
@endsection
