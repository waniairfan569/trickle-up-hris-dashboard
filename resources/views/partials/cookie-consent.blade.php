{{-- Cookie-consent banner. Strictly-necessary cookies keep you signed in; the
     choice is remembered in this browser (localStorage). Self-contained Alpine. --}}
<div x-data="cookieConsent()" x-init="init()" x-show="show" x-cloak x-transition
     class="fixed inset-x-0 bottom-0 z-[100] px-3 pb-3 sm:px-4 sm:pb-4"
     style="padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 0.75rem);">
    <div class="mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl sm:flex-row sm:items-center sm:justify-between sm:p-5 dark:border-slate-700 dark:bg-slate-800">
        <p class="text-sm text-slate-600 dark:text-slate-300">
            <span class="mr-1">🍪</span> We use strictly necessary cookies to keep you signed in, and remember small preferences on your device. Read our
            <a href="{{ route('legal.cookies') }}" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400">Cookie Policy</a>.
        </p>
        <div class="flex shrink-0 items-center gap-2">
            <button type="button" @click="decline()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Decline</button>
            <button type="button" @click="accept()" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">Accept</button>
        </div>
    </div>
</div>
<script>
    function cookieConsent() {
        return {
            show: false,
            init() {
                try { this.show = !localStorage.getItem('cookie_consent'); }
                catch (e) { this.show = false; }
            },
            accept() { this.remember('accepted'); },
            decline() { this.remember('declined'); },
            remember(v) {
                try { localStorage.setItem('cookie_consent', v); } catch (e) {}
                this.show = false;
            },
        };
    }
</script>
