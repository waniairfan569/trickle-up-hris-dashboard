@extends('layouts.hr-app')

@section('title', 'Time & Attendance')
@section('breadcrumb', 'Time & Attendance')

@section('content')
<div class="space-y-10">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Time &amp; Attendance</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Leave rules, working hours, attendance reporting and HR documents — all in one place.
        </p>
    </div>

    {{-- One titled group per former sub-menu (Time Settings · Attendance · Documents) --}}
    @foreach($sections as $section)
        <section class="space-y-4">
            <div>
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-200">{{ $section['title'] }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $section['text'] }}</p>
            </div>
            @include('partials.hub-tiles', ['tiles' => $section['tiles']])
        </section>
    @endforeach

</div>
@endsection
