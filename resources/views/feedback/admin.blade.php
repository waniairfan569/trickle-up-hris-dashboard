@extends('layouts.hr-app')

@section('title', 'Feedback & Issues')
@section('breadcrumb', 'Feedback & Issues')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="message-square-heart" class="h-6 w-6 text-brand-500"></i> Feedback &amp; Issues
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Everything employees have submitted — reply and set a status; they'll see it on their dashboard.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <!-- Status filter -->
    @php
        $tabs = [
            null => ['All', $counts['open'] + $counts['in_progress'] + $counts['resolved']],
            'open' => ['Open', $counts['open']],
            'in_progress' => ['In progress', $counts['in_progress']],
            'resolved' => ['Resolved', $counts['resolved']],
        ];
    @endphp
    <div class="flex flex-wrap items-center gap-2">
        @foreach($tabs as $key => [$label, $count])
            <a href="{{ route('feedback.admin', array_filter(['status' => $key])) }}"
               class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition {{ $status === $key ? 'bg-brand-500 text-slate-900' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' }}">
                {{ $label }}
                <span class="inline-flex items-center justify-center rounded-full px-1.5 min-w-4 h-4 text-[10px] {{ $status === $key ? 'bg-slate-900/10' : 'bg-slate-100 dark:bg-slate-700' }}">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    @forelse($feedback as $item)
        @php [$badgeLabel, $badgeClasses] = $item->statusBadge(); @endphp
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-9 w-9 shrink-0 rounded-full bg-brand-100 dark:bg-brand-500/20 flex items-center justify-center text-sm font-bold text-brand-700 dark:text-brand-400">
                            {{ strtoupper(mb_substr(optional($item->user)->full_name ?? '?', 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ optional($item->user)->full_name ?? 'Unknown employee' }}</p>
                            <p class="text-[11px] text-slate-400">{{ $item->categoryLabel() }} · {{ $item->created_at->format('d M Y, g:i A') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold shrink-0 {{ $badgeClasses }}">{{ $badgeLabel }}</span>
                </div>

                <div class="mt-4">
                    @if($item->subject)
                        <p class="text-sm font-bold text-slate-800 dark:text-white">{{ $item->subject }}</p>
                    @endif
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $item->message }}</p>
                </div>

                @if($item->admin_response)
                    @php
                        $replyMeta = collect([
                            optional($item->responder)->full_name,
                            optional($item->responded_at)->format('d M Y'),
                        ])->filter()->implode(' · ');
                    @endphp
                    <div class="mt-4 rounded-xl bg-brand-50 dark:bg-brand-500/10 border border-brand-200/60 dark:border-brand-500/20 p-3.5">
                        <p class="text-[11px] font-bold text-brand-700 dark:text-brand-400 flex items-center gap-1.5">
                            <i data-lucide="message-square-reply" class="h-3.5 w-3.5"></i>
                            Your reply{{ $replyMeta ? ' · ' . $replyMeta : '' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ $item->admin_response }}</p>
                    </div>
                @endif
            </div>

            <!-- Respond -->
            <div class="border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/60 dark:bg-slate-900/30 rounded-b-2xl px-5 sm:px-6 py-4">
                <form method="POST" action="{{ route('feedback.respond', $item) }}" class="space-y-3">
                    @csrf
                    <textarea name="admin_response" rows="2" maxlength="3000"
                              placeholder="{{ $item->admin_response ? 'Update your reply…' : 'Write a reply to the employee…' }}"
                              class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></textarea>
                    <div class="flex items-center justify-end gap-2">
                        <select name="status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="open" @selected($item->status === 'open')>Open</option>
                            <option value="in_progress" @selected($item->status === 'in_progress')>In progress</option>
                            <option value="resolved" @selected($item->status === 'resolved')>Resolved</option>
                        </select>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400 transition">
                            <i data-lucide="send" class="h-4 w-4"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl border border-dashed border-slate-200 dark:bg-slate-800 dark:border-slate-700 px-6 py-16 text-center">
            <i data-lucide="inbox" class="h-8 w-8 text-slate-300 mx-auto"></i>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No feedback {{ $status ? 'with this status' : 'yet' }}.</p>
        </div>
    @endforelse

    @if($feedback->hasPages())
        <div>{{ $feedback->links() }}</div>
    @endif
</div>
@endsection
