{{--
    One-time onboarding questionnaire for a brand-new workspace owner. Self-gates:
    shows only for a super-admin whose tenant hasn't answered/skipped yet. Included
    once in the app layout; renders nothing for everyone else.

    NOTE: x-teleport must live on a <template> tag (Alpine v3) — the x-data scope
    stays on the outer div so the pills' @click/:class stay wired after teleport.
--}}
@php
    $__ou = auth()->user();
    $__ot = \App\Tenancy\Brand::tenant();
    if (! $__ot && $__ou && $__ou->tenant_id) {
        $__ot = \App\Models\Tenant::find($__ou->tenant_id);
    }
    $__showSurvey = $__ou && ! $__ou->isOperator() && $__ou->isSuperAdmin() && $__ot && $__ot->needsOnboardingSurvey();
    $__sizes = ['1-10' => '1–10', '11-50' => '11–50', '51-200' => '51–200', '201-500' => '201–500', '500+' => '500+'];
    $__industries = ['Technology', 'Healthcare', 'Finance', 'Education', 'Retail', 'Manufacturing', 'Hospitality', 'Construction', 'Professional services', 'Non-profit', 'Other'];
    $__heard = ['Search engine', 'Social media', 'Friend or colleague', 'Advertisement', 'Event', 'Other'];
@endphp

@if($__showSurvey)
<div x-data="{ open: true, size: '' }">
    <template x-teleport="body">
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            {{-- backdrop (no click-to-close: this is a one-time step, use Skip) --}}
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

            <div x-show="open" x-transition
                 class="relative w-full max-w-lg rounded-3xl bg-white shadow-2xl dark:bg-slate-800 overflow-hidden">
                {{-- header --}}
                <div class="px-7 pt-7 pb-5 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-600 dark:bg-brand-500/15">
                        <i data-lucide="party-popper" class="h-6 w-6"></i>
                    </div>
                    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Welcome to {{ $__ot->displayName() }} 👋</h2>
                    <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">A couple of quick questions so we can tailor your workspace. Takes 20 seconds.</p>
                </div>

                <form method="POST" action="{{ route('onboarding.survey') }}" class="px-7 pb-4 space-y-5">
                    @csrf

                    {{-- Company size --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">How many people work here? <span class="text-rose-500">*</span></label>
                        <input type="hidden" name="company_size" :value="size" required>
                        <div class="grid grid-cols-5 gap-2">
                            @foreach($__sizes as $__val => $__label)
                                <button type="button" @click="size = '{{ $__val }}'"
                                        :class="size === '{{ $__val }}' ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-600 dark:text-slate-300'"
                                        class="rounded-xl border py-2.5 text-sm font-bold transition">{{ $__label }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Industry --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Industry</label>
                        <select name="industry" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="">Select an industry…</option>
                            @foreach($__industries as $__ind)
                                <option value="{{ $__ind }}">{{ $__ind }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Country --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Country</label>
                            <input type="text" name="country" maxlength="80" placeholder="e.g. Pakistan"
                                   class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        </div>
                        {{-- Heard from --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">How did you hear about us?</label>
                            <select name="heard_from" class="w-full rounded-xl border-slate-300 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <option value="">Choose one…</option>
                                @foreach($__heard as $__h)
                                    <option value="{{ $__h }}">{{ $__h }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <button type="submit" :disabled="!size"
                            :class="size ? 'bg-brand-600 hover:bg-brand-700 text-slate-900' : 'bg-slate-200 text-slate-400 cursor-not-allowed dark:bg-slate-700'"
                            class="w-full rounded-xl py-3 text-sm font-extrabold transition">Finish setup</button>
                </form>

                {{-- Skip --}}
                <div class="px-7 pb-6 text-center">
                    <form method="POST" action="{{ route('onboarding.survey.skip') }}">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition">Skip for now</button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
