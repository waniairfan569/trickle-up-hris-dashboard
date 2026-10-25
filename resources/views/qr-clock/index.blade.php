@extends('layouts.hr-app')

@section('title', 'QR clock-in points')
@section('breadcrumb', 'QR clock-in')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">QR clock-in points</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            A screen at a door or a desk showing a code people scan to clock in. The code changes every few
            seconds, so a photograph of it is useless somewhere else.
        </p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700/60">
            <thead class="bg-slate-50 dark:bg-slate-700/30">
                <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    <th class="px-5 py-3">Point</th>
                    <th class="px-5 py-3">Office</th>
                    <th class="px-5 py-3">Code changes</th>
                    <th class="px-5 py-3">Last used</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($points as $point)
                    <tr>
                        <td class="px-5 py-3 text-sm font-semibold text-slate-800 dark:text-white">{{ $point->name }}</td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ $point->officeLocation->name ?? 'Not tied to an office' }}
                        </td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">every {{ $point->window() }}s</td>
                        <td class="px-5 py-3 text-sm text-slate-500 dark:text-slate-400">
                            {{ $point->last_used_at?->diffForHumans() ?? 'never' }}
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('qr-clock.display', $point) }}" target="_blank"
                               class="text-xs font-bold text-brand-600 hover:underline dark:text-brand-400">Open the screen</a>
                            <form method="POST" action="{{ route('qr-clock.destroy', $point) }}" class="inline"
                                  onsubmit="return confirm('Remove this point? Nobody will be able to clock in here.')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-xs font-bold text-slate-400 hover:text-rose-600">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">
                            No points yet. Add one below, then open its screen on a tablet where people arrive.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('qr-clock.store') }}"
          class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        @csrf
        <h2 class="text-sm font-extrabold text-slate-800 dark:text-white">Add a point</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Name</label>
                <input type="text" name="name" required maxlength="120" placeholder="Reception, Workshop door"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Office</label>
                <select name="office_location_id" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="">Not tied to an office</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[10px] text-slate-400">Lets a scan be checked against the same geofence as a manual clock-in</p>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5 dark:text-slate-400">Code changes every</label>
                <input type="number" name="rotate_seconds" min="10" max="300" value="30"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <p class="mt-1 text-[10px] text-slate-400">Seconds. Shorter is safer; too short and a slow phone misses it</p>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button class="btn-brand">Add point</button>
        </div>
    </form>

    <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-5 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400">
        <p><strong class="text-slate-700 dark:text-slate-300">How it works.</strong> Open a point's screen on a tablet
        or monitor where people arrive and leave it there. Somebody points their phone camera at it, signs in if they
        are not already, and confirms. The clock-in is recorded exactly as a manual one is — same geofence, same late
        rules, same breaks.</p>
        <p class="mt-2"><strong class="text-slate-700 dark:text-slate-300">Why the code moves.</strong> A code that
        never changes gets photographed once and used from home for ever, which is worse than having none: it looks
        like proof of attendance and is not. These expire in seconds.</p>
        <p class="mt-2"><strong class="text-slate-700 dark:text-slate-300">NFC.</strong> Tapping a card needs a native
        app on the phone; browsers cannot read NFC on iPhone at all. That is why this is a scan rather than a tap.</p>
    </div>
</div>
@endsection
