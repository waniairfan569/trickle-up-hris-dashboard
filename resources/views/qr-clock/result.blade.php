@extends('layouts.hr-app')

@section('title', $ok ? 'Done' : 'Not recorded')
@section('breadcrumb', 'Clock in')

@section('content')
<div class="max-w-md mx-auto space-y-5">
    <div class="rounded-2xl border p-8 text-center shadow-sm
                {{ $ok ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                       : 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10' }}">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-full
                    {{ $ok ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' }}">
            <i data-lucide="{{ $ok ? 'check' : 'clock-alert' }}" class="h-7 w-7"></i>
        </div>

        <h1 class="mt-4 text-xl font-extrabold {{ $ok ? 'text-emerald-900 dark:text-emerald-200' : 'text-amber-900 dark:text-amber-200' }}">
            {{ $message }}
        </h1>

        <p class="mt-2 text-sm {{ $ok ? 'text-emerald-800/80 dark:text-emerald-200/80' : 'text-amber-900/80 dark:text-amber-200/80' }}">
            {{ $point->name }}
        </p>
    </div>

    <div class="flex justify-center gap-3">
        <a href="{{ route('dashboard') }}" class="btn-outline btn-sm">Go to dashboard</a>
        @unless($ok)
            <a href="{{ url()->previous() }}" class="btn-brand btn-sm">Try again</a>
        @endunless
    </div>
</div>
@endsection
