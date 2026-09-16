@extends('layouts.hr-app')

@section('title', 'Templates')
@section('breadcrumb', 'Templates')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Templates</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Reusable building blocks — profile layouts and saved signatures.
        </p>
    </div>

    @include('partials.hub-tiles', ['tiles' => $tiles])

</div>
@endsection
