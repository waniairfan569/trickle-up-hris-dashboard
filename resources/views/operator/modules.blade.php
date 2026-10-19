@extends('layouts.operator')

@section('title', 'Modules')
@section('breadcrumb', 'Modules')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="modulesManager()">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="layout-grid" class="h-6 w-6 text-indigo-500"></i> Modules
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">The catalog of capabilities your plans are built from. Add or edit modules here, then include them in plans on the <a href="{{ route('operator.plans') }}" class="text-indigo-600 hover:underline dark:text-indigo-400">Plans</a> page.</p>
        </div>
        <button type="button" @click="openCreate()" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700"><i data-lucide="plus" class="h-4 w-4"></i> New module</button>
    </div>

    <div class="rounded-xl border border-slate-200/70 bg-indigo-50/40 p-3 text-[12px] text-slate-500 dark:bg-indigo-500/5 dark:border-slate-700 flex items-start gap-2">
        <i data-lucide="info" class="h-4 w-4 mt-0.5 shrink-0 text-indigo-500"></i>
        <span>A module here is a <b>sellable capability</b> you can put on plans. The underlying feature must exist in the app for it to actually gate access — this list controls what you can package and price.</span>
    </div>

    {{-- Search and filters. 38 modules all arrive with the page, so filtering
         happens here rather than as a round-trip — typing narrows instantly. --}}
    <div class="flex flex-wrap items-center gap-2.5">
        <div class="relative min-w-[200px] flex-1">
            <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
            <input type="search" id="module-search" x-model="q" placeholder="Search modules, keys or functions"
                   class="w-full rounded-xl border border-slate-300 py-2.5 pl-9 pr-3 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </div>
        <select id="module-group" x-model="group" class="rounded-xl border border-slate-300 py-2.5 px-3 text-sm font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            <option value="all">All groups</option>
            @foreach($grouped->keys() as $g)
                <option value="{{ $g }}">{{ $g }}</option>
            @endforeach
        </select>
        <select id="module-status" x-model="status" class="rounded-xl border border-slate-300 py-2.5 px-3 text-sm font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            <option value="all">All statuses</option>
            <option value="active">Active</option>
            <option value="archived">Archived</option>
        </select>
        <select id="module-usage" x-model="usage" class="rounded-xl border border-slate-300 py-2.5 px-3 text-sm font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            <option value="all">On any plan</option>
            <option value="used">On a plan</option>
            <option value="unused">On no plan</option>
        </select>
        <span class="text-xs font-semibold text-slate-400" x-text="visible + ' of ' + items.length"></span>
        <button type="button" x-show="q || group !== 'all' || status !== 'all' || usage !== 'all'" x-cloak @click="q=''; group='all'; status='all'; usage='all'"
                class="text-xs font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-400">Clear</button>
    </div>

    {{-- Modules grid --}}
    @if($features->isEmpty())
        <p class="rounded-2xl border border-slate-200/80 bg-white px-5 py-10 text-center text-sm text-slate-400 dark:bg-slate-800 dark:border-slate-700">No modules yet. Add one to start building plans.</p>
    @else
        @php $i = -1; @endphp
        @foreach($grouped as $groupName => $groupFeatures)
            @php $groupIndexes = range($i + 1, $i + $groupFeatures->count()); @endphp
            <section x-show="anyVisible({{ Illuminate\Support\Js::from($groupIndexes) }})" class="space-y-3">
                <div class="flex items-baseline gap-2.5 pt-1">
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-300">{{ $groupName }}</h2>
                    <span class="text-[11px] text-slate-400" x-text="countVisible({{ Illuminate\Support\Js::from($groupIndexes) }}) + ' of {{ $groupFeatures->count() }}'"></span>
                    <span class="h-px flex-1 bg-slate-200 dark:bg-slate-700"></span>
                </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($groupFeatures as $f)
                @php $fns = $f->functions ?? []; $i++; @endphp
                <div x-show="show({{ $i }})" x-data="{ fnOpen: false }"
                     class="flex flex-col rounded-2xl border bg-white p-4 shadow-sm dark:bg-slate-800 {{ $f->is_active ? 'border-slate-200/80 dark:border-slate-700' : 'border-amber-200 bg-amber-50/30 dark:border-amber-500/25 dark:bg-amber-500/5' }}">
                    <div class="flex items-start gap-3">
                        <span class="grid place-items-center h-9 w-9 shrink-0 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"><i data-lucide="box" class="h-4 w-4"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold leading-snug text-slate-800 dark:text-white">{{ $f->label }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-slate-400 break-all">{{ $f->key }}</p>
                        </div>
                        @unless($f->is_active)
                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">Archived</span>
                        @endunless
                    </div>

                    @if($f->description)
                        <p class="mt-2.5 text-[12.5px] leading-relaxed text-slate-500 dark:text-slate-400">{{ $f->description }}</p>
                    @endif

                    @if(count($fns))
                        <button type="button" @click="fnOpen = !fnOpen" class="mt-2.5 inline-flex items-center gap-1 self-start text-[11px] font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                            <i data-lucide="list-tree" class="h-3 w-3"></i> {{ count($fns) }} function{{ count($fns)===1?'':'s' }}
                            <i data-lucide="chevron-down" class="h-3 w-3 transition" :class="fnOpen ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="fnOpen" x-cloak class="mt-2 flex flex-wrap gap-1.5">
                            @foreach($fns as $fn)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"><i data-lucide="check" class="h-3 w-3 text-indigo-400"></i>{{ $fn }}</span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Pushed to the bottom so every card's footer lines up. --}}
                    <div class="mt-auto flex items-center justify-between gap-2 pt-3.5">
                        <span class="text-[11px] font-semibold {{ $f->plans_count ? 'text-slate-500 dark:text-slate-400' : 'text-amber-600 dark:text-amber-400' }}">
                            {{ $f->plans_count ? $f->plans_count . ' plan' . ($f->plans_count === 1 ? '' : 's') : 'On no plan' }}
                        </span>
                        <div class="flex items-center gap-0.5">
                            <button type="button" title="Edit" @click="openEdit({{ Illuminate\Support\Js::from(['id'=>$f->id,'label'=>$f->label,'group'=>$f->group,'description'=>$f->description,'functions'=>$f->functions ?? []]) }})" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                            <form action="{{ route('operator.modules.toggle', $f) }}" method="POST">@csrf
                                <button title="{{ $f->is_active ? 'Archive' : 'Activate' }}" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10"><i data-lucide="{{ $f->is_active ? 'archive' : 'archive-restore' }}" class="h-4 w-4"></i></button>
                            </form>
                            <form action="{{ route('operator.modules.destroy', $f) }}" method="POST" onsubmit="return confirm('Delete “{{ $f->label }}”? It will be removed from {{ $f->plans_count }} plan(s).')">@csrf @method('DELETE')
                                <button title="Delete" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
            </section>
        @endforeach

        <p x-show="visible === 0" x-cloak class="rounded-2xl border border-slate-200/80 bg-white px-5 py-10 text-center text-sm text-slate-400 dark:bg-slate-800 dark:border-slate-700">
            No modules match that. <button type="button" @click="q=''; group='all'; status='all'; usage='all'" class="font-bold text-indigo-600 hover:underline dark:text-indigo-400">Clear the filters</button>
        </p>
    @endif

    {{-- Add / edit modal --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4" @keydown.escape.window="open=false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" @click.away="open=false">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2"><i data-lucide="layout-grid" class="h-5 w-5 text-indigo-500"></i> <span x-text="isEdit ? 'Edit module' : 'New module'"></span></h3>
            <form :action="formAction" method="POST" class="mt-4 space-y-3">@csrf
                <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Name</label>
                    <input type="text" name="label" x-model="form.label" maxlength="100" required placeholder="e.g. Payroll" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Group</label>
                    <select name="group" x-model="form.group" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach(\App\Models\PlanFeature::GROUPS as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-400">Which part of the product this belongs to. Modules are listed under their group here and on every company's page.</p>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Description <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" name="description" x-model="form.description" maxlength="255" placeholder="What this module covers" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Functions <span class="font-normal text-slate-400">(what this module includes)</span></label>
                    <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                        <template x-for="(fn, i) in form.functions" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="text" :name="'functions['+i+']'" x-model="form.functions[i]" maxlength="120" placeholder="e.g. Clock in / out" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                <button type="button" @click="form.functions.splice(i,1)" title="Remove" class="shrink-0 grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 text-lg leading-none">&times;</button>
                            </div>
                        </template>
                        <p x-show="form.functions.length === 0" class="text-xs text-slate-400 italic">No functions yet — add the capabilities this module includes.</p>
                    </div>
                    <button type="button" @click="form.functions.push('')" class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:underline"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Add function</button>
                </div>
                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" @click="open=false" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white hover:bg-indigo-700" x-text="isEdit ? 'Save' : 'Add module'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function modulesManager() {
        return {
            // Search and filter state. `items` carries one searchable record per
            // card, in the same order the cards are rendered.
            q:'', group:'all', status:'all', usage:'all',
            items: {!! Illuminate\Support\Js::from($features->map(fn ($f) => [
                'text'   => mb_strtolower(trim($f->label . ' ' . $f->key . ' ' . $f->description . ' ' . implode(' ', $f->functions ?? []))),
                'group'  => $f->group ?: 'Other',
                'active' => (bool) $f->is_active,
                'plans'  => (int) $f->plans_count,
            ])->values()) !!},
            // A section hides itself when none of its own cards are showing.
            anyVisible(indexes) { return indexes.some(i => this.show(i)); },
            countVisible(indexes) { return indexes.filter(i => this.show(i)).length; },
            show(i) {
                const it = this.items[i];
                if (!it) return false;
                if (this.group !== 'all' && it.group !== this.group) return false;
                if (this.status === 'active' && !it.active) return false;
                if (this.status === 'archived' && it.active) return false;
                if (this.usage === 'used' && it.plans === 0) return false;
                if (this.usage === 'unused' && it.plans > 0) return false;
                const term = this.q.trim().toLowerCase();
                return term === '' || it.text.includes(term);
            },
            get visible() {
                return this.items.reduce((n, _, i) => n + (this.show(i) ? 1 : 0), 0);
            },

            open:false, isEdit:false,
            storeUrl:'{{ route('operator.modules.store') }}',
            form:{ label:'', group:'Other', description:'', functions:[] },
            formAction:'{{ route('operator.modules.store') }}',
            openCreate(){ this.isEdit=false; this.form={ label:'', group:'Other', description:'', functions:[] }; this.formAction=this.storeUrl; this.open=true; },
            openEdit(f){ this.isEdit=true; this.form={ label:f.label||'', group:f.group||'Other', description:f.description||'', functions: Array.isArray(f.functions) ? [...f.functions] : [] }; this.formAction='{{ url('operator/modules') }}/'+f.id; this.open=true; this.$nextTick(()=>window.lucide&&lucide.createIcons()); },
        };
    }
</script>
@endsection
