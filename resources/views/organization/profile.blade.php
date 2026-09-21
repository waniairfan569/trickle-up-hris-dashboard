@extends('layouts.hr-app')

@section('title', 'Organization Profile')
@section('breadcrumb', 'Organization Profile')

@php
    $__status = strtolower((string) $tenant->status);
    $__statusTone = match ($__status) {
        'active'   => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'trialing' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
        'canceled' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        default    => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    $__seatLimit = $tenant->seatLimit();
    $__seatsUsed = $tenant->seatCount();
    $__tz = old('timezone', $tenant->timezone ?: config('app.timezone', 'UTC'));
    $__currency = strtoupper(old('currency', $tenant->currency ?: 'USD'));
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    @include('partials.company-general-tabs')

    {{-- Header --}}
    <div class="flex items-start gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
            <img src="{{ $tenant->logo_url ?: asset('images/logo.png') }}" alt="logo" class="h-10 w-10 object-contain">
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white truncate">{{ $tenant->displayName() }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                @if($tenant->subdomain)
                    <span class="font-mono text-xs">{{ $tenant->subdomain }}</span>
                    <span class="text-slate-300 dark:text-slate-600">·</span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold {{ $__statusTone }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ucfirst($tenant->status ?: 'unknown') }}
                </span>
                <span class="text-slate-300 dark:text-slate-600">·</span>
                <span class="text-xs font-semibold">{{ $plan?->name ?? 'Trial' }} plan</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- At-a-glance --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @php
            $__cards = [
                ['icon' => 'credit-card', 'value' => $plan?->name ?? 'Trial', 'label' => 'Current plan', 'route' => 'billing.index'],
                ['icon' => 'user-check',  'value' => $__seatLimit === 0 ? $__seatsUsed : $__seatsUsed.' / '.$__seatLimit, 'label' => 'Seats used', 'route' => 'billing.index'],
                ['icon' => 'users',       'value' => $memberCount, 'label' => 'People', 'route' => 'team.index'],
                ['icon' => 'shield',      'value' => $roleCounts->count(), 'label' => 'Roles', 'route' => 'roles.index'],
            ];
        @endphp
        @foreach($__cards as $__c)
            <a href="{{ route($__c['route']) }}" class="group rounded-2xl bg-white border border-slate-200/80 shadow-sm p-4 dark:bg-slate-800 dark:border-slate-700 hover:border-brand-300 hover:shadow transition">
                <i data-lucide="{{ $__c['icon'] }}" class="h-5 w-5 text-slate-400 group-hover:text-brand-500 transition"></i>
                <div class="mt-2 text-xl font-extrabold text-slate-900 dark:text-white truncate">{{ $__c['value'] }}</div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $__c['label'] }}</div>
            </a>
        @endforeach
    </div>

    {{-- Identity & locale — the editable part --}}
    <form method="POST" action="{{ route('organization.update') }}"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-6">
        @csrf @method('PUT')

        <div class="flex items-center gap-2 pb-1 border-b border-slate-100 dark:border-slate-700/60">
            <i data-lucide="building" class="h-5 w-5 text-brand-500"></i>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Identity &amp; locale</h2>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Legal / organization name</label>
            <input type="text" name="name" value="{{ old('name', $tenant->name) }}" maxlength="255" required
                   class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            <p class="text-[11px] text-slate-400 mt-1">Used on invoices, documents and legal records. To change the name shown in the sidebar, edit <a href="{{ route('workspace.branding') }}" class="text-brand-600 font-semibold hover:underline">Workspace Branding</a>.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Workspace address</label>
                <input type="text" value="{{ $tenant->subdomain ?: '—' }}" readonly
                       class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-500 font-mono cursor-not-allowed dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-400">
                <p class="text-[11px] text-slate-400 mt-1">Your sign-in address. Contact support to change it — it affects every link.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Notifications from</label>
                <input type="email" name="from_email" value="{{ old('from_email', $tenant->from_email) }}" maxlength="255" placeholder="hr@yourcompany.com"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <p class="text-[11px] text-slate-400 mt-1">The “from” address on emails to your team. Leave blank for the default.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Timezone</label>
                <select name="timezone" required
                        class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    @foreach(timezone_identifiers_list() as $__zone)
                        <option value="{{ $__zone }}" @selected($__zone === $__tz)>{{ $__zone }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1">Used for attendance, schedules and report dates.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Currency</label>
                <input type="text" name="currency" value="{{ $__currency }}" maxlength="3" list="__currencies" required
                       class="w-full rounded-xl border-slate-300 text-sm uppercase tracking-widest font-mono dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <datalist id="__currencies">
                    <option value="USD"><option value="EUR"><option value="GBP"><option value="PKR">
                    <option value="INR"><option value="AED"><option value="CAD"><option value="AUD"><option value="SGD">
                </datalist>
                <p class="text-[11px] text-slate-400 mt-1">3-letter code for salary, payroll and billing display.</p>
            </div>
        </div>

        @php
            $__sizes = ['1-10' => '1–10', '11-50' => '11–50', '51-200' => '51–200', '201-500' => '201–500', '500+' => '500+'];
            $__industries = ['Technology', 'Healthcare', 'Finance', 'Education', 'Retail', 'Manufacturing', 'Hospitality', 'Construction', 'Professional services', 'Non-profit', 'Other'];
            $__size = old('company_size', $tenant->company_size);
            $__industry = old('industry', $tenant->industry);
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Company size</label>
                <select name="company_size" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="">—</option>
                    @foreach($__sizes as $__val => $__label)
                        <option value="{{ $__val }}" @selected($__size === $__val)>{{ $__label }} people</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Industry</label>
                <select name="industry" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <option value="">—</option>
                    @foreach($__industries as $__ind)
                        <option value="{{ $__ind }}" @selected($__industry === $__ind)>{{ $__ind }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Country</label>
                <input type="text" name="country" value="{{ old('country', $tenant->country) }}" maxlength="80" placeholder="e.g. Pakistan"
                       class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
            </div>
        </div>

        <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">Save changes</button>
        </div>
    </form>

    {{-- Owners & admins — who can administer this org (managed in Roles & access) --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4 dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-center justify-between pb-1 border-b border-slate-100 dark:border-slate-700/60">
            <div class="flex items-center gap-2">
                <i data-lucide="user-cog" class="h-5 w-5 text-brand-500"></i>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Owners &amp; admins</h2>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ $adminCount }}</span>
            </div>
            <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/50 transition">
                <i data-lucide="settings-2" class="h-3.5 w-3.5"></i> Manage
            </a>
        </div>
        <p class="text-[11px] text-slate-400 -mt-1">Admins have full access to this workspace. To add someone or change their access, use <a href="{{ route('roles.index') }}" class="text-brand-600 font-semibold hover:underline">Roles &amp; access</a>.</p>
        <div class="space-y-2">
            @forelse($admins as $admin)
                @php $isSuper = $admin->roles->contains('slug', 'super_admin'); @endphp
                <div class="flex items-center gap-3 rounded-xl border border-slate-100 p-2.5 dark:border-slate-700/60">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 overflow-hidden dark:bg-slate-700 dark:text-slate-300">
                        @if($admin->avatar_url)
                            <img src="{{ $admin->avatar_url }}" alt="" class="h-9 w-9 object-cover">
                        @else
                            {{ strtoupper(mb_substr($admin->first_name ?: $admin->email, 0, 1)) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $admin->full_name ?: $admin->email }}</div>
                        <div class="text-xs text-slate-400 truncate">{{ $admin->email }}</div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $isSuper ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' : 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400' }}">
                        {{ $isSuper ? 'Super Admin' : 'HR Admin' }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No admins assigned yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Managed elsewhere — the rest of the org lives on its own pages --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-2 dark:bg-slate-800 dark:border-slate-700">
        <div class="px-4 pt-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">Manage elsewhere</div>
        @php
            $__links = [
                ['route' => 'workspace.branding',      'icon' => 'palette',      'title' => 'Branding',        'text' => 'Logo, accent colour and the name your team sees'],
                ['route' => 'billing.index',           'icon' => 'credit-card',  'title' => 'Billing & plan',  'text' => 'Subscription, seats, invoices and upgrades'],
                ['route' => 'team.index',              'icon' => 'users-round',  'title' => 'People',          'text' => 'Employees, invites and team structure'],
                ['route' => 'roles.index',             'icon' => 'shield-check', 'title' => 'Roles & access',  'text' => 'Who can do what across the workspace'],
                ['route' => 'office-locations.index',  'icon' => 'map-pin',      'title' => 'Office locations', 'text' => 'Where your people work'],
            ];
        @endphp
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @foreach($__links as $__l)
                <a href="{{ route($__l['route']) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/40 transition group">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 group-hover:bg-brand-50 group-hover:text-brand-600 dark:bg-slate-700 dark:text-slate-300 transition">
                        <i data-lucide="{{ $__l['icon'] }}" class="h-4 w-4"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $__l['title'] }}</div>
                        <div class="text-xs text-slate-400 truncate">{{ $__l['text'] }}</div>
                    </div>
                    <i data-lucide="chevron-right" class="h-4 w-4 text-slate-300 group-hover:text-slate-500"></i>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
