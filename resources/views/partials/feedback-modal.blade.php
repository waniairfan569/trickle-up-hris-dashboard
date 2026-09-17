{{-- Standalone "Share feedback" modal. Opened by dispatching the `open-feedback`
     window event (e.g. from the dashboard's Feedback & Suggestions card). Also
     auto-opens if a submit failed validation. History/replies live on the
     Feedback & Suggestions page. --}}
@php $feedbackAutoOpen = $errors->hasAny(['category', 'subject', 'message']); @endphp
<div x-data="{ open: {{ $feedbackAutoOpen ? 'true' : 'false' }} }" @open-feedback.window="open = true">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-transition.opacity>
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
                                    class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
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
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            @error('subject')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 mb-1.5">Details</label>
                            <textarea name="message" rows="5" maxlength="3000" required
                                      placeholder="Describe your feedback or the issue you're facing…"
                                      class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('message') }}</textarea>
                            @error('message')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
                        <button type="button" @click="open = false"
                                class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-brand-400 transition">
                            <i data-lucide="send" class="h-4 w-4"></i> Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
