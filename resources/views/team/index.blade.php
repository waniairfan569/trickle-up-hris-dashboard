@extends('layouts.hr-app')

@section('title', 'Team Management')
@section('breadcrumb', 'Team Management')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Team Management</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Everything for running your team in one place — {{ now()->format('l, F j') }}.
        </p>
    </div>

    @include('partials.hub-tiles', ['tiles' => $tiles])

</div>
@endsection
