@php
    // The signed-in employee's own submissions (most recent first).
    $myFeedback = \App\Models\Feedback::where('user_id', auth()->id())
        ->with('responder')
        ->latest()
        ->limit(10)
        ->get();
    $feedbackAutoOpen = $errors->hasAny(['category', 'subject', 'message']);
@endphp

<div x-data="{ open: {{ $feedbackAutoOpen ? 'true' : 'false' }} }"
     class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
        <h2 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <i data-lucide="message-square-heart" class="h-4 w-4 text-brand-500"></i> Feedback &amp; issues
        </h2>
        <button type="button" @click="open = true"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-bold text-slate-900 hover:bg-brand-400 transition">
            <i data-lucide="plus" class="h-3.5 w-3.5"></i> New
        </button>
    </div>

    <div class="px-6 py-4">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Have feedback, a suggestion, or facing an issue? Let HR know — you'll see their reply right here.
        </p>

        @if($myFeedback->isNotEmpty())
            <div class="mt-4 space-y-3">
                @foreach($myFeedback as $item)
                    @php [$badgeLabel, $badgeClasses] = $item->statusBadge(); @endphp
                    <div class="rounded-xl border border-slate-200/70 dark:border-slate-700/60 p-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 shrink-0">{{ $item->categoryLabel() }}</span>
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badgeClasses }}">{{ $badgeLabel }}</span>
                            </div>
                            <span class="text-[11px] text-slate-400 shrink-0">{{ $item->created_at->format('d M Y') }}</span>
                        </div>

                        @if($item->subject)
                            <p class="mt-1.5 text-sm font-bold text-slate-800 dark:text-white">{{ $item->subject }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $item->message }}</p>

                        @if($item->admin_response)
                            @php
                                $replyMeta = collect([
                                    optional($item->responder)->full_name,
                                    optional($item->responded_at)->format('d M Y'),
                                ])->filter()->implode(' · ');
                            @endphp
                            <div class="mt-2.5 rounded-lg bg-brand-50 dark:bg-brand-500/10 border border-brand-200/60 dark:border-brand-500/20 p-2.5">
                                <p class="text-[11px] font-bold text-brand-700 dark:text-brand-400 flex items-center gap-1.5">
                                    <i data-lucide="message-square-reply" class="h-3 w-3"></i>
                                    HR replied{{ $replyMeta ? ' · ' . $replyMeta : '' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ $item->admin_response }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="mt-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 px-4 py-6 text-center">
                <p class="text-xs text-slate-400">You haven't submitted anything yet.</p>
            </div>
        @endif
    </div>

    <!-- Submit modal -->
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4"
             x-transition.opacity>
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>

            <div class="relative w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200/70 dark:border-slate-700 max-h-[90vh] overflow-y-auto"
                 @keydown.escape.window="open = false">
                <form method="POST" action="{{ route('feedback.store') }}">
                    @csrf
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i data-lucide="message-square-heart" class="h-4 w-4 text-brand-500"></i> Share feedback or report an issue
                        </h3>
                        <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Category</label>
                            <select name="category"
                                    class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm focus:ring-brand-500 focus:border-brand-500">
                                @foreach(\App\Models\Feedback::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Subject <span class="font-normal text-slate-400">(optional)</span></label>
                            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="150"
                                   placeholder="A short summary"
                                   class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm focus:ring-brand-500 focus:border-brand-500">
                            @error('subject')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Details</label>
                            <textarea name="message" rows="5" maxlength="3000" required
                                      placeholder="Describe your feedback or the issue you're facing…"
                                      class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sm focus:ring-brand-500 focus:border-brand-500">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="button" @click="open = false"
                                class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400 transition">
                            <i data-lucide="send" class="h-4 w-4"></i> Send to HR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
