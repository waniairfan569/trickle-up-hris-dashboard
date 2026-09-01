@extends('layouts.operator')

@section('title', 'Modules')
@section('breadcrumb', 'Modules')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="modulesManager()">

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

    {{-- Modules list --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white shadow-sm overflow-hidden dark:bg-slate-800 dark:border-slate-700">
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($features as $f)
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 {{ $f->is_active ? '' : 'opacity-60' }}">
                    <span class="grid place-items-center h-9 w-9 shrink-0 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"><i data-lucide="box" class="h-4 w-4"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-slate-800 dark:text-white">{{ $f->label }} @unless($f->is_active)<span class="text-[10px] font-bold uppercase text-amber-600">· archived</span>@endunless</p>
                        <p class="text-[11px] text-slate-400">{{ $f->key }}@if($f->description) · {{ $f->description }}@endif</p>
                    </div>
                    <span class="text-[11px] text-slate-400">{{ $f->plans_count }} plan{{ $f->plans_count===1?'':'s' }}</span>
                    <div class="flex items-center gap-1">
                        <button type="button" title="Edit" @click="openEdit({{ Illuminate\Support\Js::from(['id'=>$f->id,'label'=>$f->label,'description'=>$f->description]) }})" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-700"><i data-lucide="pencil" class="h-4 w-4"></i></button>
                        <form action="{{ route('operator.modules.toggle', $f) }}" method="POST">@csrf
                            <button title="{{ $f->is_active ? 'Archive' : 'Activate' }}" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-500/10"><i data-lucide="{{ $f->is_active ? 'archive' : 'archive-restore' }}" class="h-4 w-4"></i></button>
                        </form>
                        <form action="{{ route('operator.modules.destroy', $f) }}" method="POST" onsubmit="return confirm('Delete “{{ $f->label }}”? It will be removed from {{ $f->plans_count }} plan(s).')">@csrf @method('DELETE')
                            <button title="Delete" class="inline-grid place-items-center h-8 w-8 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-400">No modules yet. Add one to start building plans.</p>
            @endforelse
        </div>
    </div>

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
                    <label class="block text-[11px] font-bold text-slate-500 mb-1">Description <span class="font-normal text-slate-400">(optional)</span></label>
                    <input type="text" name="description" x-model="form.description" maxlength="255" placeholder="What this module covers" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
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
        const blank = { label:'', description:'' };
        return {
            open:false, isEdit:false,
            storeUrl:'{{ route('operator.modules.store') }}',
            form:{...blank}, formAction:'{{ route('operator.modules.store') }}',
            openCreate(){ this.isEdit=false; this.form={...blank}; this.formAction=this.storeUrl; this.open=true; },
            openEdit(f){ this.isEdit=true; this.form={label:f.label||'',description:f.description||''}; this.formAction='{{ url('operator/modules') }}/'+f.id; this.open=true; },
        };
    }
</script>
@endsection
