@extends('layouts.hr-app')

@section('title', 'System Audit Logs')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">System Audit Logs</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Review system activities, configuration changes, and compliance events.</p>
        </div>
        <div class="flex items-center space-x-3">
            <button disabled class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-400 shadow-sm cursor-not-allowed dark:bg-slate-800 dark:border-slate-700 dark:text-slate-500">
                <i data-lucide="download" class="h-4 w-4"></i>
                <span>Export Report</span>
            </button>
        </div>
    </div>

    <!-- Empty State / Under Construction -->
    <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 mb-6 dark:bg-slate-800/50 ring-1 ring-slate-100 dark:ring-slate-700">
            <i data-lucide="shield-alert" class="h-8 w-8 text-slate-400 dark:text-slate-500"></i>
        </div>
        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Audit Viewer Under Construction</h2>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 max-w-lg mx-auto">
            System events and administrative actions are being securely logged in the background for compliance purposes, but the user interface for filtering and reviewing these logs is currently under development.
        </p>
        
        <div class="mt-8 flex justify-center gap-4">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm hover:bg-brand-700 transition duration-150">
                Return to Dashboard
            </a>
        </div>
    </div>

</div>
@endsection
