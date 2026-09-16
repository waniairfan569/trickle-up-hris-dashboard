@extends('layouts.hr-app')

@section('title', 'Company')
@section('breadcrumb', 'Company')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Company</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Company-wide settings — structure, forms, documents, policies, billing and API access.
        </p>
    </div>

    @include('partials.hub-tiles', ['tiles' => $tiles])

</div>
@endsection
