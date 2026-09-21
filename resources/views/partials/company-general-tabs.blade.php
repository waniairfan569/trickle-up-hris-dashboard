{{--
    "General" section tabs — shown at the top of the Company › General pages so
    Company Entities, Workspace Branding, Office Locations and Departments read as
    one section instead of four sidebar items. Active tab follows the current route.
--}}
@php
    $__generalTabs = [
        ['route' => 'organization.edit',       'match' => 'organization',      'icon' => 'building',   'label' => 'Organization'],
        ['route' => 'company-entities.index',  'match' => 'company-entities',  'icon' => 'building-2', 'label' => 'General'],
        ['route' => 'workspace.branding',      'match' => 'workspace.branding', 'icon' => 'palette',    'label' => 'Workspace Branding'],
        ['route' => 'office-locations.index',  'match' => 'office-locations',  'icon' => 'map-pin',    'label' => 'Office Locations'],
        ['route' => 'departments.index',       'match' => 'departments',       'icon' => 'network',    'label' => 'Departments'],
    ];
    $__current = request()->route()?->getName() ?? '';
@endphp

<nav class="flex gap-1 overflow-x-auto rounded-2xl bg-white border border-slate-200/80 shadow-sm p-1.5 dark:bg-slate-800 dark:border-slate-700" aria-label="General settings sections">
    <a href="{{ route('company.index') }}" class="flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-bold text-slate-400 hover:bg-slate-50 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-slate-700/50 dark:hover:text-slate-200 transition shrink-0" title="Back to Company">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
    </a>
    @foreach($__generalTabs as $__t)
        @php $__active = \Illuminate\Support\Str::startsWith($__current, $__t['match']); @endphp
        <a href="{{ route($__t['route']) }}"
           class="flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-bold transition whitespace-nowrap shrink-0 {{ $__active ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-700/50' }}">
            <i data-lucide="{{ $__t['icon'] }}" class="h-4 w-4 shrink-0"></i> {{ $__t['label'] }}
        </a>
    @endforeach
</nav>
