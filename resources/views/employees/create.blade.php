@extends('layouts.hr-app')

@section('title', 'Create New Employee')
@section('breadcrumb', 'Add Employee')

@section('content')
@php
    // A blank user lets the shared field-input partial (the same one the profile editor
    // uses) render employee_lookup / defaults without an existing record.
    $blankEmployee = new \App\Models\User();
    $blankEmployee->id = 0;

    // first/last name + login email are captured in the Account section, so skip the
    // template's "Full name" field to avoid duplication.
    $skipKeys = ['full_name'];
@endphp

<style>[x-cloak]{display:none!important}</style>

<div class="max-w-7xl mx-auto" x-data="{
    activeSection: 'account',
    sections: ['account' @foreach($templates as $t) @foreach($t->sections as $s) @if($s->fields->isNotEmpty()) , 'section_{{ $s->id }}' @endif @endforeach @endforeach],
    nextSection() {
        let i = this.sections.indexOf(this.activeSection);
        if (i < this.sections.length - 1) { this.activeSection = this.sections[i + 1]; window.scrollTo({ top: 0, behavior: 'smooth' }); }
    },
    prevSection() {
        let i = this.sections.indexOf(this.activeSection);
        if (i > 0) { this.activeSection = this.sections[i - 1]; window.scrollTo({ top: 0, behavior: 'smooth' }); }
    }
}">

    <!-- Header -->
    <div class="border-b border-slate-200 pb-5 mb-8 sm:flex sm:items-center sm:justify-between dark:border-slate-700">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Create New Employee</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Only name, work email and personal email are required — the employee can complete the rest of their profile later.
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <a href="{{ route('employees.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">Cancel</a>
            <button type="submit" form="create-employee-form" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition duration-150">
                <i data-lucide="check-circle" class="h-4 w-4"></i>
                Save Employee
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-8 rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400 flex-shrink-0"></i>
                <div class="ml-3">
                    <h3 class="text-sm font-bold text-red-800 dark:text-red-400">Please correct the following</h3>
                    <ul class="mt-2 list-disc pl-5 space-y-1 text-sm text-red-700 dark:text-red-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">

        <!-- Sidebar Navigation -->
        <div class="md:col-span-3 sticky top-6 space-y-6">
            <nav class="space-y-1">
                <div class="pb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">Account</div>
                <button type="button" @click="activeSection = 'account'"
                        :class="activeSection === 'account' ? 'bg-brand-50 text-brand-700 border-l-4 border-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent dark:text-slate-400 dark:hover:bg-slate-800/50'"
                        class="w-full flex items-center px-4 py-2.5 text-sm font-bold rounded-r-lg transition-colors text-left">
                    <i data-lucide="key-round" class="h-4 w-4 mr-3" :class="activeSection === 'account' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'"></i>
                    Account &amp; Access
                </button>
            </nav>

            @foreach($templates as $template)
                <nav class="space-y-1">
                    <div class="pb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">{{ $template->name }}</div>
                    @foreach($template->sections as $section)
                        @if($section->fields->isNotEmpty())
                            <button type="button" @click="activeSection = 'section_{{ $section->id }}'"
                                    :class="activeSection === 'section_{{ $section->id }}' ? 'bg-brand-50 text-brand-700 border-l-4 border-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent dark:text-slate-400 dark:hover:bg-slate-800/50'"
                                    class="w-full flex items-center px-4 py-2.5 text-sm font-bold rounded-r-lg transition-colors text-left">
                                <i data-lucide="layout" class="h-4 w-4 mr-3" :class="activeSection === 'section_{{ $section->id }}' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'"></i>
                                {{ $section->name }}
                            </button>
                        @endif
                    @endforeach
                </nav>
            @endforeach
        </div>

        <!-- Form Content Area -->
        <div class="md:col-span-9 relative">
            <form id="create-employee-form" action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf

                <!-- Section: Account & Access -->
                <div x-show="activeSection === 'account'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white dark:from-slate-800/50 dark:to-slate-800">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Account &amp; Access</h3>
                                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Identity and how this employee will sign in.</p>
                            </div>
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
                                <i data-lucide="user" class="h-5 w-5"></i>
                            </div>
                        </div>
                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}"
                                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white" placeholder="e.g. John">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}"
                                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white" placeholder="e.g. Doe">
                            </div>
                            <div class="md:col-span-2 rounded-xl bg-brand-50/60 border border-brand-100 px-4 py-3 text-xs text-slate-600 dark:bg-brand-500/5 dark:border-brand-500/20 dark:text-slate-300">
                                <i data-lucide="info" class="inline-block h-3.5 w-3.5 -mt-0.5 text-brand-500"></i>
                                <strong>Work email</strong> (used to sign in) is set under <em>Work information</em> and <strong>Personal email</strong> under <em>Personal information</em> — both are required.
                            </div>
                            <div class="md:col-span-2 pt-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">System Role <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                                <select name="role_id" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    <option value="">Defaults to Employee</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $role->slug)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700/60" x-data="{ onboardingMethod: '{{ old('onboarding_method', 'invite') }}' }">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 dark:text-slate-300">How should they sign in?</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    @foreach(['invite' => ['mail','Send Invitation','Emails a secure link to set their own password.'], 'set_password' => ['key','Set Password Now','Set a temporary password they change on first login.'], 'later' => ['clock','Decide Later','Create the profile without access for now.']] as $val => $meta)
                                        <label class="relative flex flex-col p-4 cursor-pointer rounded-xl border bg-white shadow-sm hover:bg-slate-50 transition dark:bg-slate-900 dark:border-slate-700 dark:hover:bg-slate-800"
                                               :class="onboardingMethod === '{{ $val }}' ? 'border-brand-500 ring-1 ring-brand-500 bg-brand-50/30 dark:bg-brand-500/10' : 'border-slate-200'">
                                            <input type="radio" name="onboarding_method" value="{{ $val }}" class="sr-only" x-model="onboardingMethod">
                                            <div class="flex items-center gap-2 mb-1">
                                                <i data-lucide="{{ $meta[0] }}" class="h-4 w-4" :class="onboardingMethod === '{{ $val }}' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'"></i>
                                                <span class="font-bold text-slate-900 text-sm dark:text-white">{{ $meta[1] }}</span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ $meta[2] }}</p>
                                        </label>
                                    @endforeach
                                </div>
                                <div x-show="onboardingMethod === 'set_password'" x-cloak class="mt-4 p-4 rounded-xl border border-slate-200 bg-slate-50 dark:bg-slate-800/50 dark:border-slate-700">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Temporary Password</label>
                                    <div class="flex gap-3">
                                        <input type="text" name="temporary_password" id="temporary_password" value="{{ old('temporary_password') }}" class="flex-1 rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                        <button type="button" onclick="document.getElementById('temporary_password').value = Math.random().toString(36).slice(-8) + Math.random().toString(36).slice(-4).toUpperCase() + '!1a'" class="px-4 py-2 bg-white border border-slate-300 rounded-xl shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300">Generate</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Default template sections (single source of truth) -->
                @foreach($templates as $template)
                    @foreach($template->sections as $section)
                        @if($section->fields->isNotEmpty())
                            <div x-show="activeSection === 'section_{{ $section->id }}'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
                                    <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white dark:from-slate-800/50 dark:to-slate-800">
                                        <div>
                                            <div class="text-[10px] font-extrabold uppercase tracking-widest text-brand-500 mb-1">{{ $template->name }}</div>
                                            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ $section->name }}</h3>
                                            @if($section->description)
                                                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">{{ $section->description }}</p>
                                            @endif
                                        </div>
                                        <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                            <i data-lucide="layout" class="h-5 w-5"></i>
                                        </div>
                                    </div>
                                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                        @foreach($section->fields as $field)
                                            @if(in_array($field->key, $skipKeys)) @continue @endif
                                            <div class="{{ in_array($field->type, ['textarea', 'multi_select', 'file']) ? 'md:col-span-2' : '' }}">
                                                @include('employees.partials.field-input', ['field' => $field, 'value' => '', 'employee' => $blankEmployee])
                                                @if(in_array($field->key, ['work_email', 'personal_email']))
                                                    <p class="mt-1 text-[10px] font-bold text-rose-500">Required</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endforeach

                <!-- Wizard controls -->
                <div class="mt-8 flex items-center justify-between">
                    <button type="button" @click="prevSection" x-show="activeSection !== 'account'" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i> Previous
                    </button>
                    <div x-show="activeSection === 'account'"></div>

                    <button type="button" @click="nextSection" x-show="activeSection !== sections[sections.length - 1]" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-700 transition dark:bg-slate-700 dark:hover:bg-slate-600">
                        Next Section <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>

                    <button type="submit" x-show="activeSection === sections[sections.length - 1]" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                        <i data-lucide="check" class="h-4 w-4"></i> Complete &amp; Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:initialized', () => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
</script>
@endsection
