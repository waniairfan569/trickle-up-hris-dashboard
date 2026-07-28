@php $nodeType = $node['type'] ?? 'person'; @endphp

@if($nodeType === 'dept')
    {{-- Department group node: clusters same-department reports of a manager. --}}
    <li x-data="{ open: true }">
        <div class="org-card org-dept" :class="open && {{ count($node['children']) ? 'true' : 'false' }} ? 'is-open' : ''"
             data-name="{{ strtolower($node['name']) }}">
            <div class="org-dept-head">
                <span class="org-dept-icon"><i data-lucide="building-2" class="h-4 w-4"></i></span>
                <div class="min-w-0">
                    <div class="org-dept-name">{{ $node['name'] }}</div>
                    <div class="org-line">{{ $node['count'] }} {{ \Illuminate\Support\Str::plural('person', $node['count']) }}</div>
                </div>
            </div>
            @if($node['count'] > 0)
                <button type="button" @click="open = !open" class="org-count" :class="open ? 'open' : 'closed'" title="Collapse / expand department">
                    {{ $node['count'] }}
                </button>
            @endif
        </div>

        @if(!empty($node['children']))
            <ul x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-cloak>
                @foreach($node['children'] as $child)
                    @include('org-chart.node', ['node' => $child])
                @endforeach
            </ul>
        @endif
    </li>
@else
@php $jt = trim((string)($node['title'] ?? '')); $showTitle = $jt !== '' && strtolower($jt) !== 'employee'; @endphp
<li x-data="{ open: true }">
    <div class="org-card" :class="open && {{ count($node['children']) ? 'true' : 'false' }} ? 'is-open' : ''"
         data-name="{{ strtolower($node['name'] . ' ' . $jt) }}">
        @if(!empty($node['avatar']))
            <img src="{{ $node['avatar'] }}" alt="{{ $node['name'] }}" class="org-avatar">
        @else
            <div class="org-avatar org-initials">{{ $node['initials'] }}</div>
        @endif

        <a href="{{ route('employees.profile', $node['id']) }}" class="org-3dot" title="Open profile"><i data-lucide="more-vertical" class="h-4 w-4"></i></a>

        <div class="org-name">{{ $node['name'] }}</div>
        @if($showTitle)<div class="org-line">{{ $jt }}</div>@endif
        @if(!empty($node['dept']))<div class="org-line">{{ $node['dept'] }}</div>@endif
        @if(!empty($node['location']))<div class="org-line">{{ $node['location'] }}</div>@endif

        @if(!empty($node['also_reports_to']))
            <div class="org-also" title="Additional (dotted-line) managers">
                <span class="org-also-label"><i data-lucide="git-branch" class="h-3 w-3"></i> Also reports to</span>
                {{ implode(', ', $node['also_reports_to']) }}
            </div>
        @endif

        @if($node['count'] > 0)
            <button type="button" @click="open = !open" class="org-count" :class="open ? 'open' : 'closed'" title="Collapse / expand team">
                {{ $node['count'] }}
            </button>
        @endif
    </div>

    @if($node['count'] > 0)
        <ul x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-cloak>
            @foreach($node['children'] as $child)
                @include('org-chart.node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
@endif
