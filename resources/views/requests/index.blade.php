@extends('layouts.hr-app')

@section('title', 'Requests')
@section('breadcrumb', 'Requests')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Requests</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Everything employees have asked for that needs an admin's answer.
        </p>
    </div>

    @if(empty($tiles))
        <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            No request features are enabled on your plan.
        </div>
    @else
        @include('partials.hub-tiles', ['tiles' => $tiles])
    @endif

</div>
@endsection
