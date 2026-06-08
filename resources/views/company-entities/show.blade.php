@extends('layouts.hr-app')

@section('title', 'Company Entity Details')
@section('breadcrumb', 'Entity Details')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div class="flex items-center gap-4">
            <div class="h-16 w-16 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center overflow-hidden dark:bg-slate-900 dark:border-slate-700 shadow-sm">
                @if($companyEntity->logo)
                    <img src="{{ Storage::url($companyEntity->logo) }}" alt="{{ $companyEntity->name }}" class="h-full w-full object-cover">
                @else
                    <i data-lucide="building-2" class="h-8 w-8 text-slate-400 dark:text-slate-500"></i>
                @endif
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">{{ $companyEntity->name }}</h2>
                    @if($companyEntity->is_primary)
                        <span class="inline-flex items-center rounded-md bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 ring-1 ring-inset ring-brand-700/10 dark:bg-brand-500/10 dark:text-brand-400 dark:ring-brand-500/20">
                            Primary Entity
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $companyEntity->legal_name ?? 'No legal name set' }}
                </p>
            </div>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="{{ route('company-entities.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                Back to List
            </a>
            <a href="{{ route('company-entities.edit', $companyEntity) }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="pencil" class="h-4 w-4"></i>
                Edit Details
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Main Details -->
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Organization Profile</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-8">
                        <div>
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Registration Number</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $companyEntity->registration_number ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Timezone</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                <i data-lucide="globe" class="h-4 w-4 text-slate-400"></i>
                                {{ $companyEntity->timezone }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Address</dt>
                            <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                {{ $companyEntity->address_line1 }}<br>
                                @if($companyEntity->address_line2) {{ $companyEntity->address_line2 }}<br> @endif
                                {{ $companyEntity->city }}, {{ $companyEntity->country }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Operations & Calendar</h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-8">
                        <div>
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Base Currency</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ strtoupper($companyEntity->currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Fiscal Year Start</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $companyEntity->fiscal_year_start ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400">Work Week Start</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white capitalize">{{ $companyEntity->work_week_start }}</dd>
                        </div>
                        <div class="sm:col-span-3 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                            <dt class="text-[10px] font-bold text-slate-500 uppercase tracking-wider dark:text-slate-400 mb-3">Working Days</dt>
                            <dd class="flex flex-wrap gap-2">
                                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                                    @php $isWorking = in_array($day, $companyEntity->working_days ?? []); @endphp
                                    <span class="inline-flex items-center rounded-md px-2.5 py-1.5 text-xs font-semibold {{ $isWorking ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500' }}">
                                        {{ $day }}
                                    </span>
                                @endforeach
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Sidebar Details -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Status</h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Entity Status</span>
                        @if($companyEntity->is_active)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2 py-1 text-xs font-bold text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                Inactive
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Headcount</span>
                        <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $companyEntity->employees()->count() }}</span>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden dark:bg-slate-800 dark:border-red-500/30">
                <div class="px-6 py-5 border-b border-red-100 bg-red-50/50 dark:border-red-500/20 dark:bg-red-500/5">
                    <h3 class="text-base font-bold text-red-800 dark:text-red-400">Danger Zone</h3>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Deactivating this entity will hide it from active selections, but preserves historical data and employee associations.
                    </p>
                    <form action="{{ route('company-entities.update', $companyEntity) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="name" value="{{ $companyEntity->name }}">
                        <input type="hidden" name="country" value="{{ $companyEntity->country }}">
                        <input type="hidden" name="timezone" value="{{ $companyEntity->timezone }}">
                        <input type="hidden" name="currency" value="{{ $companyEntity->currency }}">
                        <input type="hidden" name="work_week_start" value="{{ $companyEntity->work_week_start }}">
                        <!-- Omit is_active to set it to false -->
                        
                        <button type="submit" class="w-full inline-flex justify-center items-center gap-2 rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-100 transition dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20">
                            <i data-lucide="power" class="h-4 w-4"></i>
                            Deactivate Entity
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
