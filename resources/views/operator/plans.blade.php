@extends('layouts.operator')

@section('title', 'Plans')
@section('breadcrumb', 'Plans')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="plansManager(@js(array_keys($featureLabels)))">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="layers" class="h-6 w-6 text-indigo-500"></i> Plans
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create and manage subscription plans. Changes apply everywhere instantly — seat limits, features and pricing.</p>
        </div>
        <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 transition">
            <i data-lucide="plus" class="h-4 w-4"></i> New plan
        </button>
    </div>

    {{-- Plans grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($plans as $plan)
            <div class="flex flex-col rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-800 {{ $plan->trashed() ? 'opacity-50 border-slate-200 dark:border-slate-700' : ($plan->is_active ? 'border-slate-200/80 dark:border-slate-700' : 'border-dashed border-slate-300 dark:border-slate-600') }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ $plan->name }}</h3>
                        <p class="text-[11px] font-mono text-slate-400">{{ $plan->key }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        @if($plan->trashed())
                            <span class="text-[10px] font-bold uppercase tracking-wide rounded bg-slate-100 px-2 py-0.5 text-slate-500 dark:bg-slate-700 dark:text-slate-300">Deleted</span>
                        @elseif(!$plan->is_active)
                            <span class="text-[10px] font-bold uppercase tracking-wide rounded bg-amber-50 px-2 py-0.5 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">Archived</span>
                        @endif
                        @if($plan->is_public && $plan->is_active && !$plan->trashed())
                            <span class="text-[10px] font-bold uppercase tracking-wide rounded bg-emerald-50 px-2 py-0.5 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">Public</span>
                        @elseif(!$plan->is_public && !$plan->trashed())
                            <span class="text-[10px] font-bold uppercase tracking-wide rounded bg-slate-100 px-2 py-0.5 text-slate-500 dark:bg-slate-700 dark:text-slate-300">Private</span>
                        @endif
                    </div>
                </div>

                <div class="mt-3 flex items-baseline gap-1">
                    <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ $symbol }}{{ rtrim(rtrim(number_format($plan->price, 2), '0'), '.') }}</span>
                    <span class="text-sm text-slate-400">/ {{ $plan->interval === 'yearly' ? 'yr' : 'mo' }}</span>
                </div>

                <div class="mt-3 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                    <div class="flex items-center gap-2"><i data-lucide="users" class="h-3.5 w-3.5 text-slate-400"></i> {{ $plan->seats === 0 ? 'Unlimited' : $plan->seats }} seats</div>
                    <div class="flex items-center gap-2"><i data-lucide="check-circle" class="h-3.5 w-3.5 text-slate-400"></i>
                        @if(in_array('*', $plan->features ?? [], true)) All modules
                        @else {{ count($plan->features ?? []) }} module{{ count($plan->features ?? []) === 1 ? '' : 's' }} @endif
                    </div>
                    @unless(in_array('*', $plan->features ?? [], true))
                        <div class="flex flex-wrap gap-1 pl-5">
                            @forelse($plan->features ?? [] as $fk)
                                @isset($featureLabels[$fk])<span class="text-[10px] rounded bg-slate-100 px-1.5 py-0.5 text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $featureLabels[$fk] }}</span>@endisset
                            @empty
                                <span class="text-[10px] text-slate-400">No modules selected</span>
                            @endforelse
                        </div>
                    @endunless
                    @if($plan->trial_days > 0)<div class="flex items-center gap-2"><i data-lucide="clock" class="h-3.5 w-3.5 text-slate-400"></i> {{ $plan->trial_days }}-day trial</div>@endif
                    <div class="flex items-center gap-2"><i data-lucide="building-2" class="h-3.5 w-3.5 text-slate-400"></i> {{ $plan->tenants_count }} compan{{ $plan->tenants_count === 1 ? 'y' : 'ies' }}</div>
                </div>

                @if(!$plan->trashed())
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center gap-1.5">
                    <button type="button" @click="openEdit({{ Illuminate\Support\Js::from([
                        'id'=>$plan->id,'name'=>$plan->name,'price'=>(float)$plan->price,'currency'=>$plan->currency,
                        'interval'=>$plan->interval,'seats'=>$plan->seats,'trial_days'=>$plan->trial_days,'blurb'=>$plan->blurb,
                        'is_public'=>$plan->is_public,'features'=>$plan->features ?? [],'all_features'=>in_array('*',$plan->features ?? [],true),
                        'stripe_price_id'=>$plan->stripe_price_id,
                    ]) }})" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700"><i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit</button>

                    <form action="{{ route('operator.plans.duplicate', $plan) }}" method="POST">@csrf
                        <button title="Duplicate" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="copy" class="h-4 w-4"></i></button>
                    </form>
                    <form action="{{ route('operator.plans.toggle', $plan) }}" method="POST">@csrf
                        <button title="{{ $plan->is_active ? 'Archive' : 'Activate' }}" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10"><i data-lucide="{{ $plan->is_active ? 'archive' : 'archive-restore' }}" class="h-4 w-4"></i></button>
                    </form>
                    <form action="{{ route('operator.plans.destroy', $plan) }}" method="POST" class="ml-auto" onsubmit="return confirm('Delete “{{ $plan->name }}” permanently?')">@csrf @method('DELETE')
                        <button title="Delete" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </form>
                </div>
                @endif
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 p-16 text-center">
                <i data-lucide="layers" class="h-10 w-10 text-slate-300 dark:text-slate-600 mx-auto"></i>
                <p class="mt-3 font-bold text-slate-600 dark:text-slate-300">No plans yet</p>
                <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700"><i data-lucide="plus" class="h-4 w-4"></i> Create your first plan</button>
            </div>
        @endforelse
    </div>

    {{-- Add / edit modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4 overflow-y-auto" @keydown.escape.window="open=false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800 my-8" @click.away="open=false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="layers" class="h-5 w-5 text-indigo-500"></i> <span x-text="isEdit ? 'Edit plan' : 'New plan'"></span>
            </h3>
            <form :action="formAction" method="POST" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Name</label>
                    <input type="text" name="name" x-model="form.name" maxlength="100" required placeholder="e.g. Growth" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Price</label>
                        <input type="number" name="price" x-model="form.price" min="0" step="0.01" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Currency</label>
                        <input type="text" name="currency" x-model="form.currency" maxlength="8" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Billing</label>
                        <select name="interval" x-model="form.interval" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="monthly">Monthly</option><option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Seats <span class="font-normal text-slate-400">(0 = unlimited)</span></label>
                        <input type="number" name="seats" x-model="form.seats" min="0" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 mb-1">Trial days</label>
                        <input type="number" name="trial_days" x-model="form.trial_days" min="0" max="365" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Short blurb <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" name="blurb" x-model="form.blurb" maxlength="255" placeholder="One line shown on the pricing page" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>

                {{-- Features --}}
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-600">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        <input type="hidden" name="all_features" value="0">
                        <input type="checkbox" name="all_features" value="1" x-model="form.all_features" class="rounded border-slate-300 text-indigo-600"> All modules (full access)
                    </label>
                    <div x-show="!form.all_features" x-cloak class="mt-2">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] text-slate-400" x-text="form.features.length + ' of {{ count($featureLabels) }} modules selected'"></span>
                            <div class="flex gap-2 text-[11px] font-bold">
                                <button type="button" class="text-indigo-600 hover:underline" @click="form.features = {{ Illuminate\Support\Js::from(array_keys($featureLabels)) }}">Select all</button>
                                <button type="button" class="text-slate-400 hover:underline" @click="form.features = []">Clear</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-0.5 max-h-56 overflow-y-auto rounded-lg border border-slate-100 p-1 dark:border-slate-700">
                            @foreach($featureLabels as $key => $label)
                                <label class="flex items-center gap-2 rounded-lg px-2 py-1 text-xs hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer">
                                    <input type="checkbox" name="features[]" value="{{ $key }}" x-model="form.features" class="rounded border-slate-300 text-indigo-600">
                                    <span class="text-slate-700 dark:text-slate-200">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" x-model="form.is_public" class="rounded border-slate-300 text-indigo-600"> Public — customers can select this plan
                </label>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" @click="open=false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700" x-text="isEdit ? 'Save changes' : 'Create plan'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function plansManager(featureKeys) {
        const blank = { name:'', price:0, currency:'{{ $currency }}', interval:'monthly', seats:0, trial_days:0, blurb:'', is_public:true, features:[], all_features:false };
        return {
            open:false, isEdit:false, featureKeys,
            storeUrl:'{{ route('operator.plans.store') }}',
            form:{...blank},
            formAction:'{{ route('operator.plans.store') }}',
            openCreate(){ this.isEdit=false; this.form={...blank, features:[]}; this.formAction=this.storeUrl; this.open=true; },
            openEdit(p){
                this.isEdit=true;
                this.form={ name:p.name||'', price:p.price??0, currency:p.currency||'{{ $currency }}', interval:p.interval||'monthly',
                    seats:p.seats??0, trial_days:p.trial_days??0, blurb:p.blurb||'', is_public:!!p.is_public,
                    features:(p.features||[]).filter(f=>f!=='*'), all_features:!!p.all_features };
                this.formAction='{{ url('operator/plans') }}/'+p.id;
                this.open=true;
            },
        };
    }
</script>
@endsection
