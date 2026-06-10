@extends('layouts.hr-app')

@section('title', 'Create New Employee')
@section('breadcrumb', 'Add Employee')

@section('content')
<div class="max-w-7xl mx-auto" x-data="{ 
    activeSection: 'basic_info',
    sections: ['basic_info', 'job_placement', 'account_setup'],
    
    nextSection() {
        let currentIndex = this.sections.indexOf(this.activeSection);
        if (currentIndex < this.sections.length - 1) {
            this.activeSection = this.sections[currentIndex + 1];
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    },
    
    prevSection() {
        let currentIndex = this.sections.indexOf(this.activeSection);
        if (currentIndex > 0) {
            this.activeSection = this.sections[currentIndex - 1];
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
}">

    <!-- Header -->
    <div class="border-b border-slate-200 pb-5 mb-8 sm:flex sm:items-center sm:justify-between dark:border-slate-700">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Create New Employee</h2>
            <p class="mt-1 flex items-center text-sm text-slate-500 dark:text-slate-400">
                Register a new team member. Navigate through the sections to complete their profile.
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
                <div class="flex-shrink-0">
                    <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-400">There were errors with your submission</h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
        <!-- Sidebar Navigation -->
        <div class="md:col-span-3 sticky top-6 space-y-6">
            <nav class="space-y-1">
                <div class="pb-2 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-slate-500">Core Details</div>
                
                <button type="button" @click="activeSection = 'basic_info'" 
                        :class="activeSection === 'basic_info' ? 'bg-brand-50 text-brand-700 border-l-4 border-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-slate-900'"
                        class="w-full flex items-center px-4 py-2.5 text-sm font-bold rounded-r-lg transition-colors text-left">
                    <i data-lucide="user" class="h-4 w-4 mr-3" :class="activeSection === 'basic_info' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'"></i>
                    Basic Information
                </button>
                
                <button type="button" @click="activeSection = 'job_placement'" 
                        :class="activeSection === 'job_placement' ? 'bg-brand-50 text-brand-700 border-l-4 border-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-slate-900'"
                        class="w-full flex items-center px-4 py-2.5 text-sm font-bold rounded-r-lg transition-colors text-left">
                    <i data-lucide="briefcase" class="h-4 w-4 mr-3" :class="activeSection === 'job_placement' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'"></i>
                    Job & Placement
                </button>

                <button type="button" @click="activeSection = 'account_setup'" 
                        :class="activeSection === 'account_setup' ? 'bg-brand-50 text-brand-700 border-l-4 border-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-l-4 border-transparent dark:text-slate-400 dark:hover:bg-slate-800/50 dark:hover:text-slate-900'"
                        class="w-full flex items-center px-4 py-2.5 text-sm font-bold rounded-r-lg transition-colors text-left">
                    <i data-lucide="key" class="h-4 w-4 mr-3" :class="activeSection === 'account_setup' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400'"></i>
                    Account Setup
                </button>
            </nav>

            <p class="px-4 pt-2 text-[10px] leading-relaxed text-slate-400 dark:text-slate-500">
                Only name, work email and job title are required. The rest of the profile
                (personal details, documents, etc.) is completed afterwards on the
                employee's profile page.
            </p>
        </div>

        <!-- Form Content Area -->
        <div class="md:col-span-9 relative">
            <form id="create-employee-form" action="{{ route('employees.store') }}" method="POST">
                @csrf

                <!-- Section: Basic Information -->
                <div x-show="activeSection === 'basic_info'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white dark:from-slate-800/50 dark:to-slate-800">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Basic Information</h3>
                                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Essential contact and system access details.</p>
                            </div>
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
                                <i data-lucide="user" class="h-5 w-5"></i>
                            </div>
                        </div>
                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}" required
                                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors"
                                       placeholder="e.g. John">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" required
                                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors"
                                       placeholder="e.g. Doe">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Work Email <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <i data-lucide="mail" class="h-4 w-4 text-slate-400"></i>
                                    </div>
                                    <input type="email" name="email" value="{{ old('email') }}" required
                                           class="w-full rounded-xl border-slate-300 pl-10 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors"
                                           placeholder="john.doe@company.com">
                                </div>
                            </div>
                            <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">System Role <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                                <select name="role_id" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                    <option value="">Defaults to Employee</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                            {{ ucwords(str_replace('_', ' ', $role->slug)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">The role dictates the permissions this employee will have across the HR modules.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Job & Placement -->
                <div x-show="activeSection === 'job_placement'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white dark:from-slate-800/50 dark:to-slate-800">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Job & Placement</h3>
                                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Position, compensation, and organizational mapping.</p>
                            </div>
                            <div class="h-10 w-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
                                <i data-lucide="briefcase" class="h-5 w-5"></i>
                            </div>
                        </div>
                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Job Title <span class="text-red-500">*</span></label>
                                <input type="text" name="job_title" value="{{ old('job_title') }}" required
                                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors"
                                       placeholder="e.g. Senior Software Engineer">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Department <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                                <select name="department_id" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                    <option value="">Select department...</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Job Location</label>
                                <div class="flex gap-2">
                                    <select name="job_location_id" id="job_location_select"
                                            class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                        <option value="">— Select location —</option>
                                        @foreach($jobLocations as $loc)
                                            <option value="{{ $loc->id }}" {{ old('job_location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->display_name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" onclick="toggleAddLocation()"
                                            class="shrink-0 inline-flex items-center gap-1 rounded-xl bg-slate-800 px-3 py-2 text-sm font-bold text-white hover:bg-slate-700 transition dark:bg-slate-700 dark:hover:bg-slate-600">
                                        <i data-lucide="plus" class="h-4 w-4"></i> New
                                    </button>
                                </div>

                                <div id="add-location-form" class="hidden mt-3 p-4 rounded-xl border border-slate-200 bg-slate-50 dark:bg-slate-800/50 dark:border-slate-700 space-y-3">
                                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider dark:text-slate-300">Add new location</p>
                                    <input type="text" id="new_loc_name" placeholder="Location name (e.g. Lahore Office)"
                                           class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <input type="text" id="new_loc_city" placeholder="City (optional)"
                                               class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                        <select id="new_loc_country"
                                                class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                            @foreach(\App\Models\JobLocation::COUNTRIES as $code => $cname)
                                                <option value="{{ $code }}">{{ $cname }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <input type="checkbox" id="new_loc_remote" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                                        Remote / Work from home
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="saveNewLocation()"
                                                class="inline-flex items-center gap-1 rounded-xl bg-brand-600 px-3 py-2 text-sm font-bold text-slate-900 hover:bg-brand-700 transition">Save &amp; Select</button>
                                        <button type="button" onclick="toggleAddLocation()"
                                                class="inline-flex items-center rounded-xl bg-white px-3 py-2 text-sm font-bold text-slate-700 border border-slate-300 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">Cancel</button>
                                    </div>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">Where this employee is based. You can add a new location without leaving the form.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Location <span class="text-slate-400 normal-case font-medium">(optional)</span></label>
                                <select name="location_id" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                    <option value="">Select location...</option>
                                    @foreach($locations as $loc)
                                        <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                            {{ $loc->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $tzGroups = app(\App\Services\TimezoneService::class)->getTimezoneList();
                                $companyTz = optional(\App\Models\CompanyEntity::primary())->timezone ?? config('app.timezone');
                            @endphp
                            <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700/60"
                                 x-data="{ customTz: {{ old('use_custom_timezone') ? 'true' : 'false' }} }">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    {{-- Ensures a value is sent when the box is unchecked --}}
                                    <input type="hidden" name="use_custom_timezone" value="0">
                                    <input type="checkbox" name="use_custom_timezone" value="1" x-model="customTz"
                                           class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                                    <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Custom timezone</span>
                                </label>

                                <div x-show="!customTz" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                                    Using company timezone: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $companyTz }}</span>
                                </div>

                                <div x-show="customTz" x-cloak class="mt-3">
                                    <input type="text" id="emp-tz-search" placeholder="Search timezone (e.g. London, Dubai)…"
                                           class="w-full mb-2 rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                    <select name="timezone" id="emp-tz-select" size="6"
                                            class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                                        @foreach($tzGroups as $region => $zones)
                                            <optgroup label="{{ $region }}">
                                                @foreach($zones as $id => $label)
                                                    <option value="{{ $id }}" {{ old('timezone') === $id ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">This affects how this employee's attendance times are displayed.</p>
                            </div>

                            <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Base Salary</label>
                                <div class="relative rounded-xl shadow-sm">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <span class="text-slate-500 sm:text-sm font-bold">PKR</span>
                                    </div>
                                    <input type="hidden" name="salary_currency" value="PKR">
                                    <input type="number" step="0.01" name="salary" value="{{ old('salary') }}"
                                           class="w-full rounded-xl border-slate-300 pl-14 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors"
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Account Setup -->
                <div x-show="activeSection === 'account_setup'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 dark:bg-slate-800 dark:border-slate-700/80 overflow-hidden">
                        <div class="px-8 py-6 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white dark:from-slate-800/50 dark:to-slate-800">
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Account Setup</h3>
                                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">How should this employee log in?</p>
                            </div>
                        </div>
                        <div class="p-8 space-y-6" x-data="{ onboardingMethod: 'invite' }">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Option: Invite -->
                                <label class="relative flex flex-col p-4 cursor-pointer rounded-xl border border-slate-200 bg-white shadow-sm hover:bg-slate-50 peer-checked:border-brand-500 peer-checked:ring-1 peer-checked:ring-brand-500 transition-all dark:bg-slate-900 dark:border-slate-700 dark:hover:bg-slate-800" :class="onboardingMethod === 'invite' ? 'border-brand-500 ring-1 ring-brand-500 bg-brand-50/30 dark:bg-brand-500/10 dark:border-brand-500' : ''">
                                    <input type="radio" name="onboarding_method" value="invite" class="sr-only" x-model="onboardingMethod">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="mail" class="h-5 w-5" :class="onboardingMethod === 'invite' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400 dark:text-slate-500'"></i>
                                            <span class="font-bold text-slate-900 text-sm dark:text-white">Send Invitation</span>
                                        </div>
                                        <div class="h-4 w-4 rounded-full border flex items-center justify-center transition-colors" :class="onboardingMethod === 'invite' ? 'border-brand-600 dark:border-brand-400' : 'border-slate-300 dark:border-slate-600'">
                                            <div class="h-2 w-2 rounded-full bg-brand-600 dark:bg-brand-400" x-show="onboardingMethod === 'invite'"></div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Sends a secure email with a link for the employee to set their own password. Highly recommended.</p>
                                </label>

                                <!-- Option: Set Password -->
                                <label class="relative flex flex-col p-4 cursor-pointer rounded-xl border border-slate-200 bg-white shadow-sm hover:bg-slate-50 peer-checked:border-brand-500 peer-checked:ring-1 peer-checked:ring-brand-500 transition-all dark:bg-slate-900 dark:border-slate-700 dark:hover:bg-slate-800" :class="onboardingMethod === 'set_password' ? 'border-brand-500 ring-1 ring-brand-500 bg-brand-50/30 dark:bg-brand-500/10 dark:border-brand-500' : ''">
                                    <input type="radio" name="onboarding_method" value="set_password" class="sr-only" x-model="onboardingMethod">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="key" class="h-5 w-5" :class="onboardingMethod === 'set_password' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400 dark:text-slate-500'"></i>
                                            <span class="font-bold text-slate-900 text-sm dark:text-white">Set Password Now</span>
                                        </div>
                                        <div class="h-4 w-4 rounded-full border flex items-center justify-center transition-colors" :class="onboardingMethod === 'set_password' ? 'border-brand-600 dark:border-brand-400' : 'border-slate-300 dark:border-slate-600'">
                                            <div class="h-2 w-2 rounded-full bg-brand-600 dark:bg-brand-400" x-show="onboardingMethod === 'set_password'"></div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Set a temporary password. The employee will be forced to change it on their first login.</p>
                                </label>

                                <!-- Option: Later -->
                                <label class="relative flex flex-col p-4 cursor-pointer rounded-xl border border-slate-200 bg-white shadow-sm hover:bg-slate-50 peer-checked:border-brand-500 peer-checked:ring-1 peer-checked:ring-brand-500 transition-all dark:bg-slate-900 dark:border-slate-700 dark:hover:bg-slate-800" :class="onboardingMethod === 'later' ? 'border-brand-500 ring-1 ring-brand-500 bg-brand-50/30 dark:bg-brand-500/10 dark:border-brand-500' : ''">
                                    <input type="radio" name="onboarding_method" value="later" class="sr-only" x-model="onboardingMethod">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <i data-lucide="clock" class="h-5 w-5" :class="onboardingMethod === 'later' ? 'text-brand-600 dark:text-brand-400' : 'text-slate-400 dark:text-slate-500'"></i>
                                            <span class="font-bold text-slate-900 text-sm dark:text-white">Decide Later</span>
                                        </div>
                                        <div class="h-4 w-4 rounded-full border flex items-center justify-center transition-colors" :class="onboardingMethod === 'later' ? 'border-brand-600 dark:border-brand-400' : 'border-slate-300 dark:border-slate-600'">
                                            <div class="h-2 w-2 rounded-full bg-brand-600 dark:bg-brand-400" x-show="onboardingMethod === 'later'"></div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Create the profile without granting access. You can send an invitation later.</p>
                                </label>
                            </div>

                            <div x-show="onboardingMethod === 'set_password'" x-collapse>
                                <div class="mt-6 p-5 rounded-xl border border-slate-200 bg-slate-50 dark:bg-slate-800/50 dark:border-slate-700">
                                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Temporary Password <span class="text-red-500">*</span></label>
                                    <div class="flex gap-3">
                                        <div class="relative flex-1">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i data-lucide="lock" class="h-4 w-4 text-slate-400"></i>
                                            </div>
                                            <input type="text" name="temporary_password" id="temporary_password" :required="onboardingMethod === 'set_password'" class="w-full pl-10 rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                        </div>
                                        <button type="button" onclick="document.getElementById('temporary_password').value = Math.random().toString(36).slice(-8) + Math.random().toString(36).slice(-8).toUpperCase() + '!1a'" class="px-4 py-2 bg-white border border-slate-300 rounded-xl shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700 transition-colors">Generate</button>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">You will need to manually securely communicate this password to the employee.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Profile fields are intentionally NOT collected on the create form.
                     The employee is created with the core credentials above; the full
                     profile (every section of the default template) is completed afterwards
                     on the profile page — the single template-driven source of truth for
                     both view and edit. This avoids duplicating template fields here. --}}
                @foreach([] as $template)
                    @foreach($template->sections as $section)
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
                                        <div class="{{ $field->type === 'textarea' ? 'md:col-span-2' : '' }}">
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">
                                                {{ $field->name }} @if($field->is_required) <span class="text-red-500">*</span> @endif
                                            </label>
                                            
                                            @if($field->type === 'text' || $field->type === 'number' || $field->type === 'email' || $field->type === 'date')
                                                <input type="{{ $field->type }}" 
                                                       name="dynamic_fields[{{ $field->id }}]" 
                                                       {{ $field->is_required ? 'required' : '' }}
                                                       placeholder="{{ $field->placeholder }}"
                                                       class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                            
                                            @elseif($field->type === 'textarea')
                                                <textarea name="dynamic_fields[{{ $field->id }}]" 
                                                          rows="3" 
                                                          {{ $field->is_required ? 'required' : '' }}
                                                          placeholder="{{ $field->placeholder }}"
                                                          class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors"></textarea>
                                            
                                            @elseif($field->type === 'select')
                                                <select name="dynamic_fields[{{ $field->id }}]" 
                                                        {{ $field->is_required ? 'required' : '' }}
                                                        class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                                    <option value="">Select...</option>
                                                    @if(is_array($field->options))
                                                        @foreach($field->options as $option)
                                                            <option value="{{ is_array($option) ? ($option['value'] ?? $option['name'] ?? $option['label'] ?? '') : $option }}">
                                                                {{ is_array($option) ? ($option['name'] ?? $option['label'] ?? '') : $option }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                
                                            @elseif($field->type === 'multi_select')
                                                <select name="dynamic_fields[{{ $field->id }}][]" multiple
                                                        {{ $field->is_required ? 'required' : '' }}
                                                        class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white transition-colors">
                                                    @if(is_array($field->options))
                                                        @foreach($field->options as $option)
                                                            <option value="{{ is_array($option) ? ($option['value'] ?? $option['name'] ?? $option['label'] ?? '') : $option }}">
                                                                {{ is_array($option) ? ($option['name'] ?? $option['label'] ?? '') : $option }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                <p class="text-[10px] text-slate-500 mt-1">Hold Ctrl/Cmd to select multiple options.</p>
                                            @endif
                                            
                                            @if($field->help_text)
                                                <p class="mt-1.5 text-[10px] text-slate-500 dark:text-slate-400">{{ $field->help_text }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach

                <!-- Action Controls within the form -->
                <div class="mt-8 flex items-center justify-between">
                    <button type="button" @click="prevSection" x-show="activeSection !== 'basic_info'" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Previous
                    </button>
                    <!-- Spacer if prev is hidden -->
                    <div x-show="activeSection === 'basic_info'"></div>
                    
                    <button type="button" @click="nextSection" x-show="activeSection !== sections[sections.length - 1]" class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-slate-700 transition dark:bg-slate-700 dark:hover:bg-slate-600">
                        Next Section
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>
                    
                    <!-- Submit button only prominent on the very last section, though also available in the header -->
                    <button type="submit" x-show="activeSection === sections[sections.length - 1]" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        Complete & Save Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Re-initialize Lucide icons when alpine DOM updates
    document.addEventListener('alpine:initialized', () => {
        if(typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // ---- Inline "add job location" from the employee form ----
    function toggleAddLocation() {
        document.getElementById('add-location-form').classList.toggle('hidden');
    }
    async function saveNewLocation() {
        const name = document.getElementById('new_loc_name').value.trim();
        if (!name) { alert('Location name is required'); return; }
        const token = document.querySelector('meta[name=csrf-token]').content;
        const body = {
            name,
            city: document.getElementById('new_loc_city').value,
            country: document.getElementById('new_loc_country').value,
            is_remote: document.getElementById('new_loc_remote').checked ? 1 : 0,
            _token: token
        };
        try {
            const res = await fetch('/job-locations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.id) {
                const select = document.getElementById('job_location_select');
                const opt = new Option(data.display_name, data.id, true, true);
                select.add(opt);
                select.value = data.id;
                toggleAddLocation();
                document.getElementById('new_loc_name').value = '';
                document.getElementById('new_loc_city').value = '';
            } else {
                alert((data.errors && Object.values(data.errors)[0][0]) || 'Could not add location.');
            }
        } catch (e) {
            alert('A network error occurred while adding the location.');
        }
    }

    // Searchable filter for the employee custom-timezone dropdown.
    (function () {
        const search = document.getElementById('emp-tz-search');
        const select = document.getElementById('emp-tz-select');
        if (!search || !select) return;
        search.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            select.querySelectorAll('optgroup').forEach(function (group) {
                let visible = 0;
                group.querySelectorAll('option').forEach(function (opt) {
                    const match = opt.textContent.toLowerCase().includes(q) || opt.value.toLowerCase().includes(q);
                    opt.hidden = !match;
                    if (match) visible++;
                });
                group.hidden = visible === 0;
            });
        });
    })();
</script>
@endsection
