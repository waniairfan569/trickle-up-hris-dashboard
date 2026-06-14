@extends('layouts.hr-app')

@section('title', 'Inbox')
@section('breadcrumb', 'Inbox')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Inbox</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Your notifications, important items and to-dos.</p>
        </div>
        @if($counts['unread'] > 0)
            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">
                    <i data-lucide="check-check" class="h-4 w-4"></i> Mark all read
                </button>
            </form>
        @endif
    </div>

    <!-- Folder tabs -->
    @php
        $tabs = [
            'inbox'     => ['Inbox', 'inbox', $counts['inbox']],
            'important' => ['Important', 'star', $counts['important']],
            'todos'     => ['To-dos', 'check-square', $counts['todos']],
        ];
    @endphp
    <div class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700">
        @foreach($tabs as $key => [$label, $icon, $count])
            <a href="{{ route('notifications.index', ['tab' => $key]) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold border-b-2 -mb-px transition {{ $tab === $key ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-slate-400 hover:text-slate-600' }}">
                <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                {{ $label }}
                @if($count > 0)
                    <span class="rounded-full bg-slate-100 text-slate-500 text-[10px] px-1.5 py-0.5 font-bold dark:bg-slate-700 dark:text-slate-300">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        @forelse($notifications as $n)
            @php
                $isUnread = is_null($n->read_at);
                $icon = $n->data['icon'] ?? ($tab === 'todos' ? 'check-square' : 'bell');
                $title = $n->data['title'] ?? 'Notification';
                $message = $n->data['message'] ?? ($n->data['body'] ?? '');
                $action = $n->data['action_url'] ?? ($n->data['action'] ?? null);
            @endphp
            <div class="flex items-start gap-4 px-6 py-4 border-b border-slate-100 last:border-0 dark:border-slate-700/60 {{ $isUnread ? 'bg-brand-50/40 dark:bg-brand-500/5' : '' }}">
                <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl {{ $isUnread ? 'bg-brand-100 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700' }}">
                    <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $title }}</p>
                        @if($isUnread)<span class="h-2 w-2 rounded-full bg-brand-500 flex-shrink-0"></span>@endif
                    </div>
                    @if($message)<p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $message }}</p>@endif
                    <div class="flex items-center gap-3 mt-2">
                        <span class="text-[10px] text-slate-400">{{ $n->created_at->diffForHumans() }}</span>
                        @if($action)
                            <a href="{{ $action }}" class="text-[11px] font-bold text-brand-600 hover:text-brand-700">Open</a>
                        @endif
                        @if($isUnread)
                            <form action="{{ route('notifications.mark-read', $n->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-[11px] font-bold text-slate-400 hover:text-slate-600">Mark read</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3">
                    <i data-lucide="{{ $tab === 'todos' ? 'check-square' : ($tab === 'important' ? 'star' : 'inbox') }}" class="h-7 w-7"></i>
                </div>
                <p class="text-sm font-bold text-slate-600 dark:text-slate-300">
                    @if($tab === 'important') No important notifications
                    @elseif($tab === 'todos') You're all caught up
                    @else No notifications yet
                    @endif
                </p>
                <p class="text-xs text-slate-400 mt-1">New items will appear here.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
