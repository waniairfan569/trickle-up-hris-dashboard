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

    <p class="text-center text-xs text-slate-400">
        You scanned the code at {{ $point->name }}@if($point->officeLocation), {{ $point->officeLocation->name }}@endif.
    </p>
</div>
@endsection
