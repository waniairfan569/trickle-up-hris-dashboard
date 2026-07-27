@extends('layouts.hr-app')

@section('title', 'Send for signature')
@section('breadcrumb', 'Document Templates')

@section('content')
<style>[x-cloak]{display:none!important}</style>

@php
    $employeeOpts = $employees->map(fn ($e) => [
        'id' => (string) $e->id,
        'label' => trim(($e->last_name ? $e->last_name . ', ' : '') . $e->first_name) . ($e->job_title ? ' — ' . $e->job_title : '') . ($e->id === auth()->id() ? ' (You)' : ''),
    ])->values();

    $roleOpts = [
        ['value' => 'role:employee', 'label' => 'Employee (the person you send to)'],
        ['value' => 'role:line_manager', 'label' => 'Line manager'],
        ['value' => 'role:hr_admin', 'label' => 'HR admin (you)'],
        ['value' => 'role:me_now', 'label' => 'Me (now)'],
        ['value' => 'role:sender', 'label' => 'Sender'],
    ];
    $empChoiceOpts = $employees->map(fn ($e) => [
        'value' => 'emp:' . $e->id,
        'label' => trim(($e->last_name ? $e->last_name . ', ' : '') . $e->first_name),
    ])->values();
    $initialSigners = (old('signers') ? collect(old('signers'))->map(fn ($s) => ($s['signer_type'] ?? 'role') === 'employee' ? 'emp:' . ($s['employee_id'] ?? '') : 'role:' . ($s['role'] ?? 'employee'))->all()
        : ($signerChoices->count() ? $signerChoices->all() : ['role:employee']));
@endphp
<script>
    window.__sendEmployees = @json($employeeOpts);
    window.__signerRoleOpts = @json($roleOpts);
    window.__signerEmpOpts = @json($empChoiceOpts);
    window.__initialSigners = @json($initialSigners);
</script>

<div class="max-w-2xl mx-auto space-y-6" x-data="sendFlow()">
    @include('company-documents.partials.builder-steps', ['template' => $documentTemplate, 'current' => 4])

    <div class="flex items-center gap-3">
        <a href="{{ route('company-documents.preview-sign', $documentTemplate->companyDocument) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700" title="Back to preview"><i data-lucide="arrow-left" class="h-5 w-5"></i></a>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Send for signature</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $documentTemplate->name }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/20">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('company-documents.send', $documentTemplate->companyDocument) }}"
          class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 dark:bg-slate-800 dark:border-slate-700 space-y-6">
        @csrf

        {{-- 1. Signers --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Signers</span>
                <button type="button" @click="addSigner()" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:underline"><i data-lucide="plus" class="h-3.5 w-3.5"></i> Add signer</button>
            </div>
            <div class="space-y-2">
                <template x-for="(s, i) in signers" :key="'sg' + i">
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-700" x-text="i + 1"></span>
                        <select x-model="s.choice" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <optgroup label="By role">
                                <template x-for="r in roleOpts" :key="r.value"><option :value="r.value" x-text="r.label"></option></template>
                            </optgroup>
                            <optgroup label="Specific employee">
                                <template x-for="e in empOpts" :key="e.value"><option :value="e.value" x-text="e.label"></option></template>
                            </optgroup>
                        </select>
                        <button type="button" @click="removeSigner(i)" x-show="signers.length > 1" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-700"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </div>
                </template>
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">Roles resolve to real people for the chosen employee (Employee → them, Line manager → their manager, HR admin → you).</p>

            {{-- Hidden signer inputs --}}
            <div class="hidden" aria-hidden="true">
                <template x-for="(s, i) in signers" :key="'sh' + i">
                    <span>
                        <input type="hidden" :name="`signers[${i}][signer_type]`" :value="s.choice.startsWith('emp:') ? 'employee' : 'role'">
                        <input type="hidden" :name="`signers[${i}][role]`" :value="s.choice.startsWith('role:') ? s.choice.slice(5) : ''">
                        <input type="hidden" :name="`signers[${i}][employee_id]`" :value="s.choice.startsWith('emp:') ? s.choice.slice(4) : ''">
                    </span>
                </template>
            </div>
        </div>

        @if($companyDoc)
        {{-- 2. Access --}}
        <div class="border-t border-slate-100 dark:border-slate-700/60 pt-5">
            <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Who can access this?</span>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                @foreach([['company_wide','Company-wide','All employees','users'],['department','Department','Selected departments','building-2'],['specific_users','Specific employees','Hand-pick people','user-check']] as [$val,$label,$desc,$icon])
                    <label class="cursor-pointer rounded-xl border p-3 transition" :class="access === '{{ $val }}' ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-600'">
                        <input type="radio" name="access_level" value="{{ $val }}" x-model="access" class="sr-only">
                        <div class="flex items-center gap-2 text-slate-800 dark:text-white"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i><span class="text-sm font-bold">{{ $label }}</span></div>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $desc }}</p>
                    </label>
                @endforeach
            </div>
            <div x-show="access === 'department'" x-cloak class="mt-3">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-40 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                    @foreach($departments as $d)
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input type="checkbox" name="departments[]" value="{{ $d->id }}" @checked(in_array($d->id, $companyDoc->accessRecords->where('access_type','department')->pluck('access_id')->map(fn($i)=>(int)$i)->all())) class="rounded border-slate-300 text-brand-600">{{ $d->name }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div x-show="access === 'specific_users'" x-cloak class="mt-3">
                <input type="text" x-model="userSearch" placeholder="Search employees…" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm mb-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-600 p-3">
                    @foreach($accessUsers as $u)
                        @php $name = trim($u->first_name.' '.$u->last_name); @endphp
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200" data-name="{{ Str::lower($name) }}" x-show="userSearch === '' || $el.dataset.name.includes(userSearch.toLowerCase())">
                            <input type="checkbox" name="users[]" value="{{ $u->id }}" @checked(in_array($u->id, $companyDoc->accessRecords->where('access_type','user')->pluck('access_id')->map(fn($i)=>(int)$i)->all())) class="rounded border-slate-300 text-brand-600">{{ $name }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 3. Settings --}}
        <div class="border-t border-slate-100 dark:border-slate-700/60 pt-5 space-y-3">
            <span class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Settings</span>
            <label class="flex items-center justify-between cursor-pointer">
                <span class="text-sm font-semibold text-slate-800 dark:text-white">Requires acknowledgment</span>
                <input type="checkbox" name="requires_acknowledgment" value="1" @checked($companyDoc->requires_acknowledgment) class="rounded border-slate-300 text-brand-600 h-5 w-5">
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Expires at <span class="text-slate-400 font-normal normal-case">(optional)</span></label><input type="date" name="expires_at" value="{{ optional($companyDoc->expires_at)->format('Y-m-d') }}" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white"></div>
                <label class="flex items-center gap-2 cursor-pointer pt-6">
                    <input type="checkbox" name="is_active" value="1" @checked($companyDoc->is_active) class="rounded border-slate-300 text-brand-600 h-5 w-5">
                    <span class="text-sm font-semibold text-slate-800 dark:text-white">Active (visible to employees)</span>
                </label>
            </div>
        </div>
        @endif

        {{-- 4. Send to --}}
        <div class="border-t border-slate-100 dark:border-slate-700/60 pt-5">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Send to employee</label>
            <input type="hidden" name="employee" :value="employeeId">
            <div @click.outside="open = false" class="relative">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-left dark:border-slate-600 dark:bg-slate-900">
                    <span :class="employeeId ? 'text-slate-700 dark:text-white' : 'text-slate-400'" x-text="label()"></span>
                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 transition" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-cloak x-transition.origin.top
                     class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:bg-slate-800 dark:border-slate-700">
                    <input x-model="q" type="text" placeholder="Search employees…" class="w-full rounded-lg border border-slate-300 text-xs py-1.5 px-2 dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    <div class="max-h-60 overflow-y-auto mt-2 space-y-0.5">
                        <template x-for="e in employees.filter(o => o.label.toLowerCase().includes(q.toLowerCase()))" :key="e.id">
                            <button type="button" @click="employeeId = e.id; open = false"
                                    class="w-full text-left px-2 py-1.5 rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                    :class="employeeId === e.id ? 'bg-brand-50 text-brand-700 font-semibold dark:bg-brand-500/10' : 'text-slate-700 dark:text-slate-200'">
                                <span x-text="e.label"></span>
                            </button>
                        </template>
                        <p x-show="!employees.length" class="text-xs text-slate-400 px-2 py-1">No eligible employees for this template's scope.</p>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" :disabled="!employeeId" :class="!employeeId && 'opacity-50 cursor-not-allowed'"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700">
            <i data-lucide="send" class="h-4 w-4"></i> Send for signature
        </button>
    </form>
</div>

<script>
    function sendFlow() {
        return {
            employees: window.__sendEmployees || [],
            roleOpts: window.__signerRoleOpts || [],
            empOpts: window.__signerEmpOpts || [],
            signers: (window.__initialSigners || ['role:employee']).map(c => ({ choice: c })),
            employeeId: '', open: false, q: '',
            access: @json(old('access_level', $companyDoc->access_level ?? 'company_wide')),
            userSearch: '',
            label() { const e = this.employees.find(o => o.id === this.employeeId); return e ? e.label : 'Select an employee…'; },
            addSigner() { this.signers.push({ choice: 'role:employee' }); },
            removeSigner(i) { if (this.signers.length > 1) this.signers.splice(i, 1); },
        };
    }
</script>
@endsection
