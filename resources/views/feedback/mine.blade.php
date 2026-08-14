@extends('layouts.hr-app')

@section('title', 'Feedback & Suggestions')
@section('breadcrumb', 'Feedback & Suggestions')

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
            <i data-lucide="message-square-heart" class="h-6 w-6 text-brand-500"></i> Feedback &amp; Suggestions
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Share feedback, a suggestion, or report an issue — HR replies right here.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center gap-2 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400"><i data-lucide="check-circle" class="h-5 w-5"></i>{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Two equal-height cards: submit form (left) · my submissions (right) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">

        {{-- LEFT: submit form --}}
        <form method="POST" action="{{ route('feedback.store') }}" class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 flex flex-col h-full">
            @csrf
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">New submission</h2>

            <div class="space-y-4 flex-1 mt-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach(\App\Models\Feedback::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Subject <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                    <input type="text" name="subject" value="{{ old('subject') }}" maxlength="150" placeholder="A short summary" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Details</label>
                    <textarea name="message" rows="6" maxlength="3000" required placeholder="Describe your feedback or the issue you're facing…" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('message') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end pt-5 mt-auto">
                <button type="submit" class="btn-brand"><i data-lucide="send" class="h-4 w-4"></i> Send to HR</button>
            </div>
        </form>

        {{-- RIGHT: my submissions --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700 flex flex-col h-full overflow-hidden">
            <div class="flex items-center justify-between gap-2 px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">My submissions</h2>
                @if($feedback->total())<span class="text-[11px] font-semibold text-slate-400">{{ $feedback->total() }} total</span>@endif
            </div>

            <div class="flex-1 flex flex-col{{ $feedback->count() ? ' divide-y divide-slate-100 dark:divide-slate-700/60' : '' }}">
                @forelse($feedback as $item)
                    @php [$badgeLabel, $badgeClasses] = $item->statusBadge(); @endphp
                    <div class="px-6 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 shrink-0">{{ $item->categoryLabel() }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badgeClasses }}">{{ $badgeLabel }}</span>
                            </div>
                            <span class="text-[11px] text-slate-400 shrink-0">{{ $item->created_at->format('d M Y') }}</span>
                        </div>

                        @if($item->subject)
                            <p class="mt-2 text-sm font-bold text-slate-800 dark:text-white">{{ $item->subject }}</p>
                        @endif
                        <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $item->message }}</p>

                        @if($item->admin_response)
                            @php
                                $replyMeta = collect([
                                    optional($item->responder)->full_name,
                                    optional($item->responded_at)->format('d M Y'),
                                ])->filter()->implode(' · ');
                            @endphp
                            <div class="mt-3 rounded-xl bg-brand-50 dark:bg-brand-500/10 border border-brand-200/60 dark:border-brand-500/20 p-3.5">
                                <p class="text-[11px] font-bold text-brand-700 dark:text-brand-400 flex items-center gap-1.5">
                                    <i data-lucide="message-square-reply" class="h-3.5 w-3.5"></i>
                                    HR replied{{ $replyMeta ? ' · ' . $replyMeta : '' }}
                                </p>
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ $item->admin_response }}</p>
                            </div>
                        @endif

                        @if($item->status !== 'resolved')
                            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex justify-end">
                                <form method="POST" action="{{ route('feedback.cancel', $item) }}" onsubmit="return confirm('Cancel this submission? It will be withdrawn and HR will no longer see it.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-rose-500 hover:text-rose-700 transition">
                                        <i data-lucide="x" class="h-3.5 w-3.5"></i> Cancel submission
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex-1 flex flex-col items-center justify-center text-center px-6 py-14">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-slate-400 dark:bg-slate-700 mb-3"><i data-lucide="message-square-heart" class="h-7 w-7"></i></div>
                        <p class="text-sm font-bold text-slate-600 dark:text-slate-300">Nothing submitted yet</p>
                        <p class="text-xs text-slate-400 mt-1">Use the form to send your first feedback or suggestion.</p>
                    </div>
                @endforelse
            </div>

            @if($feedback->hasPages())
                <div class="px-6 py-3 border-t border-slate-100 dark:border-slate-700/60">{{ $feedback->links() }}</div>
            @endif
        </div>

    </div>
</div>
@endsection
