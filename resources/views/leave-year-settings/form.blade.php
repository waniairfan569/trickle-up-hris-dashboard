@extends('layouts.hr-app')

@section('title', ($setting->exists ? 'Edit' : 'New') . ' Leave Year Setting')
@section('breadcrumb', 'Leave Year & Encashment')

@section('content')
<style>[x-cloak]{display:none!important}</style>
@php
    $policyDays = $policies->mapWithKeys(fn ($p) => [$p->id => (float) $p->days_per_year]);
    $inputCls = 'rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 dark:border-slate-600 dark:text-white';
@endphp
<script>window.__policyDays = @json($policyDays);</script>

<div class="max-w-3xl mx-auto space-y-6" x-data="leaveYearForm()">
    <a href="{{ route('leave-year-settings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400"><i data-lucide="arrow-left" class="h-4 w-4"></i> All settings</a>

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ $setting->exists ? route('leave-year-settings.update', $setting) : route('leave-year-settings.store') }}" class="space-y-6">
        @csrf
        @if($setting->exists) @method('PUT') @endif

        {{-- 1. Policy --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">1 · Policy</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Leave policy <span class="text-rose-500">*</span></label>
                    <select name="policy_id" x-model="policyId" required class="w-full {{ $inputCls }}">
                        <option value="">Select policy…</option>
                        @foreach($policies as $p)
                            <option value="{{ $p->id }}" @selected(old('policy_id', $setting->policy_id) == $p->id)>{{ $p->name }} ({{ rtrim(rtrim(number_format($p->days_per_year, 1), '0'), '.') }} days/yr)</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Company entity <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                    <select name="company_entity_id" class="w-full {{ $inputCls }}">
                        <option value="">Whole workspace</option>
                        @foreach($entities as $e)<option value="{{ $e->id }}" @selected(old('company_entity_id', $setting->company_entity_id) == $e->id)>{{ $e->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Setting name <span class="text-rose-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $setting->name) }}" required maxlength="120" placeholder="e.g. Annual Leave 2025-26" class="w-full {{ $inputCls }}">
            </div>
        </div>

        {{-- 2. Leave year window --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">2 · Leave year starts</h2>
            <div class="grid grid-cols-2 gap-4 sm:w-2/3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Month</label>
                    <select name="year_start_month" x-model.number="startMonth" class="w-full {{ $inputCls }}">
                        @foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected(old('year_start_month', $setting->year_start_month) == $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Day</label>
                    <input type="number" name="year_start_day" min="1" max="28" value="{{ old('year_start_day', $setting->year_start_day) }}" class="w-full {{ $inputCls }}">
                </div>
            </div>
            <p class="text-[11px] text-slate-400">The leave year runs 12 months from this date; renewal fires on it automatically each year.</p>
        </div>

        {{-- 3. Encashment rules --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">3 · Year-end encashment</h2>
            @php $type = old('encashment_type', $setting->encashment_type); @endphp
            <div class="space-y-2">
                <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition" :class="encType === 'percent_of_annual' ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                    <input type="radio" name="encashment_type" value="percent_of_annual" x-model="encType" class="mt-0.5">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-slate-800 dark:text-white">Percentage of annual allocation</span>
                        <span class="block text-xs text-slate-400 mt-0.5">Cap = % × annual days — NOT of remaining. Anything above the cap lapses.</span>
                        <span x-show="encType === 'percent_of_annual'" x-cloak class="mt-2 flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                            <input type="number" step="0.01" min="0" max="100" x-model.number="encValue" @click.stop class="w-24 {{ $inputCls }}"> %
                            <span class="text-slate-400" x-text="percentExample()"></span>
                        </span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition" :class="encType === 'full_remaining' ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                    <input type="radio" name="encashment_type" value="full_remaining" x-model="encType" class="mt-0.5">
                    <span><span class="block text-sm font-bold text-slate-800 dark:text-white">Full remaining leaves</span>
                    <span class="block text-xs text-slate-400 mt-0.5">Every remaining day is encashed — nothing lapses.</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition" :class="encType === 'fixed_days' ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                    <input type="radio" name="encashment_type" value="fixed_days" x-model="encType" class="mt-0.5">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-slate-800 dark:text-white">Fixed days cap</span>
                        <span class="block text-xs text-slate-400 mt-0.5">At most this many days are encashed, regardless of remaining.</span>
                        <span x-show="encType === 'fixed_days'" x-cloak class="mt-2 flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                            Max <input type="number" step="0.5" min="0" x-model.number="encValue" @click.stop class="w-24 {{ $inputCls }}"> days
                        </span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition" :class="encType === 'none' ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                    <input type="radio" name="encashment_type" value="none" x-model="encType" class="mt-0.5">
                    <span><span class="block text-sm font-bold text-slate-800 dark:text-white">No encashment</span>
                    <span class="block text-xs text-slate-400 mt-0.5">Remaining leaves simply lapse at year end.</span></span>
                </label>
            </div>
            {{-- single computed value for every type --}}
            <input type="hidden" name="encashment_value" :value="encType === 'full_remaining' ? 100 : (encType === 'none' ? 0 : encValue)">
            <div class="pt-1">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Working days per month <span class="text-slate-400 normal-case font-medium">(for the daily rate = salary ÷ this)</span></label>
                <input type="number" name="working_days_per_month" min="1" max="31" value="{{ old('working_days_per_month', $setting->working_days_per_month) }}" class="w-32 {{ $inputCls }}">
            </div>
        </div>

        {{-- 4. Carry forward --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <label class="flex items-center justify-between cursor-pointer">
                <span><span class="block text-sm font-bold text-slate-800 dark:text-white">4 · Carry forward remaining days</span>
                <span class="block text-xs text-slate-400 mt-0.5">Days not encashed can move into the new year (up to a max).</span></span>
                <input type="checkbox" name="carry_forward_enabled" value="1" x-model="carry" class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
            <div x-show="carry" x-cloak>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Max days <span class="text-slate-400 normal-case font-medium">(blank = unlimited)</span></label>
                <input type="number" step="0.5" min="0" name="carry_forward_max_days" value="{{ old('carry_forward_max_days', $setting->carry_forward_max_days) }}" class="w-32 {{ $inputCls }}">
            </div>
        </div>

        {{-- 5. Pro-rata --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <label class="flex items-center justify-between cursor-pointer">
                <span><span class="block text-sm font-bold text-slate-800 dark:text-white">5 · Pro-rata for mid-year joiners</span>
                <span class="block text-xs text-slate-400 mt-0.5">(annual ÷ 12) × months remaining, with a joining-day cutoff.</span></span>
                <input type="checkbox" name="pro_rata_enabled" value="1" x-model="proRata" class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
            <div x-show="proRata" x-cloak class="space-y-3">
                <div class="grid grid-cols-2 gap-4 sm:w-2/3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Cutoff day</label>
                        <input type="number" name="pro_rata_cutoff_day" min="1" max="28" x-model.number="cutoff" class="w-full {{ $inputCls }}">
                        <p class="text-[10px] text-slate-400 mt-1">Join on/before this day → that month counts. After → starts next month.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Rounding</label>
                        <select name="pro_rata_round_to" x-model="rounding" class="w-full {{ $inputCls }}">
                            <option value="none" @selected(old('pro_rata_round_to', $setting->pro_rata_round_to) === 'none')>None (1 decimal)</option>
                            <option value="half" @selected(old('pro_rata_round_to', $setting->pro_rata_round_to) === 'half')>Nearest half day</option>
                            <option value="full" @selected(old('pro_rata_round_to', $setting->pro_rata_round_to) === 'full')>Round up to full day</option>
                        </select>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden" x-show="annualDays() > 0" x-cloak>
                    <p class="px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50 dark:bg-slate-900/40">Live preview — allocation by joining month (annual = <span x-text="annualDays()"></span> days)</p>
                    <div class="max-h-64 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="text-left font-bold text-slate-400 border-b border-slate-100 dark:border-slate-700/60">
                                <th class="px-4 py-1.5">Join window</th><th class="px-4 py-1.5 text-right">Months</th><th class="px-4 py-1.5 text-right">Pro-rata days</th><th class="px-4 py-1.5 text-right">Encash cap</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-600 dark:text-slate-300">
                                <template x-for="row in previewRows()" :key="row.label">
                                    <tr>
                                        <td class="px-4 py-1.5 font-semibold" x-text="row.label"></td>
                                        <td class="px-4 py-1.5 text-right" x-text="row.months"></td>
                                        <td class="px-4 py-1.5 text-right" x-text="row.days + ' days'"></td>
                                        <td class="px-4 py-1.5 text-right" x-text="row.cap"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- 6. Auto renewal + active --}}
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-3">
            <label class="flex items-center justify-between cursor-pointer">
                <span><span class="block text-sm font-bold text-slate-800 dark:text-white">6 · Automatic renewal</span>
                <span class="block text-xs text-slate-400 mt-0.5">The 1 AM scheduler runs the renewal when the year-start date arrives.</span></span>
                <input type="checkbox" name="auto_renewal_enabled" value="1" @checked(old('auto_renewal_enabled', $setting->auto_renewal_enabled)) class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
            <label class="flex items-center justify-between cursor-pointer border-t border-slate-100 dark:border-slate-700/60 pt-3">
                <span class="text-sm font-semibold text-slate-800 dark:text-white">Setting is active</span>
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $setting->is_active ?? true)) class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('leave-year-settings.index') }}" class="rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">Cancel</a>
            <button type="submit" class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700">{{ $setting->exists ? 'Save changes' : 'Save settings' }}</button>
        </div>
    </form>
</div>

<script>
    function leaveYearForm() {
        return {
            policyId: @json((string) old('policy_id', $setting->policy_id ?? '')),
            startMonth: {{ (int) old('year_start_month', $setting->year_start_month ?? 7) }},
            encType: @json(old('encashment_type', $setting->encashment_type ?? 'percent_of_annual')),
            encValue: {{ (float) old('encashment_value', $setting->encashment_value ?? 10) }},
            carry: {{ old('carry_forward_enabled', $setting->carry_forward_enabled ?? false) ? 'true' : 'false' }},
            proRata: {{ old('pro_rata_enabled', $setting->pro_rata_enabled ?? true) ? 'true' : 'false' }},
            cutoff: {{ (int) old('pro_rata_cutoff_day', $setting->pro_rata_cutoff_day ?? 15) }},
            rounding: @json(old('pro_rata_round_to', $setting->pro_rata_round_to ?? 'half')),
            months: ['January','February','March','April','May','June','July','August','September','October','November','December'],

            annualDays() { return parseFloat((window.__policyDays || {})[this.policyId] || 0); },
            roundDays(x) {
                if (this.rounding === 'half') return Math.round(x * 2) / 2;
                if (this.rounding === 'full') return Math.ceil(x);
                return Math.round(x * 10) / 10;
            },
            capFor(alloc) {
                if (this.encType === 'percent_of_annual') return (Math.round(alloc * (this.encValue / 100) * 10) / 10) + ' days';
                if (this.encType === 'fixed_days') return 'max ' + this.encValue + ' days';
                if (this.encType === 'full_remaining') return 'all remaining';
                return '—';
            },
            percentExample() {
                const a = this.annualDays();
                if (!a) return '';
                return '→ e.g. ' + this.encValue + '% of ' + a + ' = ' + (Math.round(a * (this.encValue / 100) * 10) / 10) + ' days cap';
            },
            previewRows() {
                const a = this.annualDays();
                if (!a) return [];
                const rows = [];
                for (let i = 0; i < 12; i++) {
                    const mIdx = ((this.startMonth - 1) + i) % 12;
                    const name = this.months[mIdx].slice(0, 3);
                    const early = 12 - i;
                    const late = Math.max(0, 11 - i);
                    const dEarly = this.roundDays((a / 12) * early);
                    const dLate = this.roundDays((a / 12) * late);
                    rows.push({ label: name + ' 1–' + this.cutoff, months: early, days: dEarly, cap: this.capFor(dEarly) });
                    rows.push({ label: name + ' ' + (this.cutoff + 1) + '+', months: late, days: dLate, cap: this.capFor(dLate) });
                }
                return rows;
            },
        };
    }
</script>
@endsection
