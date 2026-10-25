@extends('layouts.hr-app')

@section('title', 'Clock in')
@section('breadcrumb', 'Clock in')

@section('content')
<div class="max-w-md mx-auto space-y-5">
    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $point->name }}</p>
        <h1 class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-white">{{ $employee->full_name }}</h1>

        @if($record && $record->clock_in)
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Clocked in at {{ app(\App\Services\TimezoneService::class)->toUserTime($record->clock_in, $employee)->format('H:i') }}
                @if($record->clock_out)
                    · out at {{ app(\App\Services\TimezoneService::class)->toUserTime($record->clock_out, $employee)->format('H:i') }}
                @endif
            </p>
        @else
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Not clocked in yet today</p>
        @endif

        <form method="POST" action="{{ route('qr-clock.punch', $point) }}" class="mt-6" x-data="{ sending: false }"
              x-init="
                  if (navigator.geolocation) {
                      navigator.geolocation.getCurrentPosition(
                          p => { $refs.lat.value = p.coords.latitude; $refs.lng.value = p.coords.longitude; },
                          () => {}, { timeout: 4000 }
                      );
                  }
              "
              @submit="sending = true">
            @csrf
            <input type="hidden" name="c" value="{{ $code }}">
            <input type="hidden" name="action" value="{{ $action }}">
            {{-- Location is recorded where the phone offers it and never waited
                 for: somebody standing at the screen has already proved where
                 they are, and a denied permission must not stop them. --}}
            <input type="hidden" name="lat" x-ref="lat">
            <input type="hidden" name="lng" x-ref="lng">

            <button type="submit" x-bind:disabled="sending"
                    class="w-full rounded-2xl px-6 py-4 text-lg font-extrabold text-white shadow-sm transition
                           {{ $action === 'out' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}
                           disabled:opacity-60">
                <span x-show="!sending">{{ $action === 'out' ? 'Clock out' : 'Clock in' }}</span>
                <span x-show="sending" x-cloak>One moment…</span>
            </button>
        </form>
    </div>

    {{-- A shift nobody is on today. Its own form, deliberately outside the one
         above: the clock-in button stays a single tap that nothing else can
         complicate or hold up. --}}
    @if(!empty($openToday) && $openToday->isNotEmpty())
        @php $spare = $openToday->first(); @endphp
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/20 dark:bg-amber-500/5">
            <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
                Nobody is on {{ $spare->shift->name }} today
            </p>
            <p class="mt-1 text-sm text-amber-700 dark:text-amber-400/80">
                {{ $spare->user->first_name }} is away, and you have nothing rostered.
                It runs {{ substr($spare->shift->start_time, 0, 5) }}–{{ substr($spare->shift->end_time, 0, 5) }}.
            </p>
            <form method="POST" action="{{ route('shifts.claim') }}" class="mt-4">
                @csrf
                <input type="hidden" name="date" value="{{ $spare->date->toDateString() }}">
                <input type="hidden" name="shift_id" value="{{ $spare->shift->id }}">
                <input type="hidden" name="covers_user_id" value="{{ $spare->user->id }}">
                <button type="submit" class="w-full rounded-xl border border-amber-300 bg-white px-4 py-3 text-sm font-extrabold text-amber-800 transition hover:bg-amber-100 dark:border-amber-500/30 dark:bg-transparent dark:text-amber-300 dark:hover:bg-amber-500/10">
                    I'm covering it
                </button>
            </form>
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-center text-sm font-semibold text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-400">
            {{ session('error') }}
        </div>
    @endif

    <p class="text-center text-xs text-slate-400">
        You scanned the code at {{ $point->name }}@if($point->officeLocation), {{ $point->officeLocation->name }}@endif.
    </p>
</div>
@endsection
