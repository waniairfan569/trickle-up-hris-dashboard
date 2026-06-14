@php $children = $byManager[$node->id] ?? collect(); @endphp
<li x-data="{ open: true }">
    <div class="org-card" data-name="{{ strtolower($node->full_name . ' ' . ($node->job_title ?? '')) }}">
        @if($node->avatar_url)
            <img src="{{ $node->avatar_url }}" alt="{{ $node->full_name }}" class="org-avatar">
        @else
            <div class="org-avatar org-initials">{{ $node->initials }}</div>
        @endif
        <a href="{{ route('employees.profile', $node->id) }}" class="org-name hover:underline">{{ $node->full_name }}</a>
        <div class="org-title">{{ $node->job_title ?: 'Employee' }}</div>
        @if($node->department)
            <div class="org-dept">{{ $node->department->name }}</div>
        @endif
        @if($children->isNotEmpty())
            <button type="button" @click="open = !open" class="org-toggle" title="Toggle direct reports">
                <span x-text="open ? '−' : '+'"></span>&nbsp;{{ $children->count() }} report{{ $children->count() > 1 ? 's' : '' }}
            </button>
        @endif
    </div>

    @if($children->isNotEmpty())
        <ul x-show="open" x-cloak>
            @foreach($children as $child)
                @include('org-chart.node', ['node' => $child, 'byManager' => $byManager])
            @endforeach
        </ul>
    @endif
</li>
