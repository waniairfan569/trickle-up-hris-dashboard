@php
    $isSuper = $role->slug === \App\Models\Role::SUPER_ADMIN;
    $moduleLabel = fn ($m) => \Illuminate\Support\Str::of($m)->replace('_', ' ')->title();
@endphp

<div class="space-y-6" x-data="{ }">
    @if($errors->any())
        <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- Role details --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-4">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Role name @unless($role->is_system)<span class="text-rose-500">*</span>@endunless</label>
            <input type="text" name="name" value="{{ old('name', $role->name) }}" maxlength="60" @if($role->is_system) disabled @else required @endif
                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white disabled:bg-slate-100 disabled:text-slate-500 dark:disabled:bg-slate-700">
            @if($role->is_system)<p class="text-[11px] text-slate-400 mt-1">System role — the name is fixed, but you can edit its description and permissions.</p>@endif
        </div>
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Description</label>
            <input type="text" name="description" value="{{ old('description', $role->description) }}" maxlength="255"
                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
        </div>
    </div>

    {{-- Permissions --}}
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h2 class="text-sm font-bold text-slate-800 dark:text-white">Permissions</h2>
            @unless($isSuper)
                <div class="flex items-center gap-3 text-xs font-semibold">
                    <button type="button" @click="document.querySelectorAll('.perm-cb:not(:disabled)').forEach(c => c.checked = true)" class="text-brand-600 hover:text-brand-700">Select all</button>
                    <span class="text-slate-300">·</span>
                    <button type="button" @click="document.querySelectorAll('.perm-cb:not(:disabled)').forEach(c => c.checked = false)" class="text-slate-500 hover:text-slate-700">Clear</button>
                </div>
            @endunless
        </div>

        @if($isSuper)
            <div class="m-6 rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-300 flex items-start gap-2">
                <i data-lucide="shield-check" class="h-5 w-5 shrink-0"></i>
                <span>Super Admin always has <b>every</b> permission — this can't be changed, so no employee action is ever locked out for them.</span>
            </div>
        @endif

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            @foreach($modules as $module => $perms)
                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1.5"><i data-lucide="folder" class="h-3.5 w-3.5"></i> {{ $moduleLabel($module) }}</h3>
                    <div class="space-y-1.5">
                        @foreach($perms as $perm)
                            <label class="flex items-start gap-2.5 rounded-lg px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-700/40 cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="perm-cb mt-0.5 rounded border-slate-300 text-brand-600"
                                       @checked(in_array($perm->id, $granted)) @if($isSuper) disabled checked @endif>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ $perm->name }}</span>
                                    @if($perm->description)<span class="block text-[11px] text-slate-400">{{ $perm->description }}</span>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('roles.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-200">Cancel</a>
        <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 hover:bg-brand-700"><i data-lucide="save" class="h-4 w-4 inline"></i> Save</button>
    </div>
</div>
