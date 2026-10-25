@extends('layouts.hr-app')

@section('title', 'Clock in')
@section('breadcrumb', 'Clock in')

@section('content')
<div class="max-w-md mx-auto space-y-5" x-data="tapClock()">
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

        {{-- Unlike the scanned code, nothing here can go ahead without a
             location: the tag is fixed, so being on site is the only proof
             there is. The button stays out of reach until the phone answers. --}}
        <div class="mt-6">
            <template x-if="state === 'asking'">
                <p class="flex items-center justify-center gap-2 rounded-2xl bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-500 dark:bg-slate-900/40 dark:text-slate-400">
                    <i data-lucide="loader-circle" class="h-4 w-4 animate-spin"></i>
                    Checking you are at {{ $point->officeLocation->name ?? 'the site' }}…
                </p>
            </template>

            <template x-if="state === 'denied'">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-left dark:border-amber-500/20 dark:bg-amber-500/5">
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-300">Location is switched off</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-400/80">
                        Tapping the tag only works on site, so your phone has to say where it is.
                        Allow location for this site in your browser, then tap the tag again.
                    </p>
                    <button type="button" @click="locate()" class="mt-3 w-full rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-bold text-amber-800 dark:border-amber-500/30 dark:bg-transparent dark:text-amber-300">
                        Try again
                    </button>
                </div>
            </template>

            <form method="POST" action="{{ route('nfc-clock.punch', [$point, $token]) }}"
                  x-show="state === 'ready'" x-cloak @submit="sending = true" style="display:none">
                @csrf
                <input type="hidden" name="action" value="{{ $action }}">
                <input type="hidden" name="lat" :value="lat">
                <input type="hidden" name="lng" :value="lng">

                <button type="submit" x-bind:disabled="sending"
                        class="w-full rounded-2xl px-6 py-4 text-lg font-extrabold text-white shadow-sm transition
                               {{ $action === 'out' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}
                               disabled:opacity-60">
                    <span x-show="!sending">{{ $action === 'out' ? 'Clock out' : 'Clock in' }}</span>
                    <span x-show="sending" x-cloak>One moment…</span>
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400">
        You tapped the tag at {{ $point->name }}@if($point->officeLocation), {{ $point->officeLocation->name }}@endif.
    </p>
</div>

<script>
function tapClock() {
    return {
        state: 'asking', sending: false, lat: null, lng: null,

        init() { this.locate(); },

        locate() {
            this.state = 'asking';

            if (!navigator.geolocation) {
                this.state = 'denied';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                p => {
                    this.lat = p.coords.latitude;
                    this.lng = p.coords.longitude;
                    this.state = 'ready';
                    this.$nextTick(() => window.lucide && window.lucide.createIcons());
                },
                () => { this.state = 'denied'; },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
            );
        },
    };
}
</script>
@endsection
