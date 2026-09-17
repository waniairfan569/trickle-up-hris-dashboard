{{-- Popup wrapper around the existing "Need a login code?" widget.
     Opened by dispatching the `open-code-request` window event (e.g. from the
     dashboard's Request Login Code card). Reuses the real widget form + logic. --}}
<div x-data="{ open: false }" @open-code-request.window="open = true">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto" @keydown.escape.window="open = false">
                <button type="button" @click="open = false"
                        class="absolute right-3 top-3 z-10 rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
                @include('partials.code-request-widget')
            </div>
        </div>
    </template>
</div>
