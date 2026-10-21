@extends('layouts.hr-app')

@section('title', 'Documents')
@section('breadcrumb', 'Documents')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Documents</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Everything you send to people and get back — signed, acknowledged or answered.
            Grouped by what you start with, because that is what decides which one you want.
        </p>
    </div>

    @foreach($groups as $group)
        @continue(empty($group['tiles']))
        <div class="space-y-3">
            <div class="flex items-baseline gap-2.5">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-300">{{ $group['label'] }}</h2>
                <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
            </div>
            @include('partials.hub-tiles', ['tiles' => $group['tiles']])
        </div>
    @endforeach

</div>
@endsection
