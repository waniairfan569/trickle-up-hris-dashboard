@extends('layouts.hr-app')

@section('title', 'Edit Company Entity')
@section('breadcrumb', 'Edit Entity')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                Edit Entity: <span class="text-brand-600">{{ $companyEntity->name }}</span>
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Update legal details, location, and operating parameters.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('company-entities.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
                Back to List
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-400">There were errors with your submission</h3>
                    <ul class="mt-2 list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('company-entities.update', $companyEntity) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')

        <!-- Identity Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Company Identity</h3>
                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Core legal details and branding.</p>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Display Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $companyEntity->name) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Legal Name</label>
                    <input type="text" name="legal_name" value="{{ old('legal_name', $companyEntity->legal_name) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Registration Number</label>
                    <input type="text" name="registration_number" value="{{ old('registration_number', $companyEntity->registration_number) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Company Logo</label>
                    <div class="flex items-center gap-4">
                        @if($companyEntity->logo)
                            <img src="{{ Storage::url($companyEntity->logo) }}" alt="Logo" class="h-10 w-10 object-cover rounded-lg border border-slate-200">
                        @endif
                        <input type="file" name="logo" accept="image/*" class="w-full rounded-xl border-slate-300 shadow-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-slate-700 dark:file:text-slate-300 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Location & Region</h3>
                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Headquarters address and regional settings.</p>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Address Line 1</label>
                    <input type="text" name="address_line1" value="{{ old('address_line1', $companyEntity->address_line1) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Address Line 2</label>
                    <input type="text" name="address_line2" value="{{ old('address_line2', $companyEntity->address_line2) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">City</label>
                    <input type="text" name="city" value="{{ old('city', $companyEntity->city) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Country Code (2 letters) <span class="text-red-500">*</span></label>
                    <input type="text" name="country" value="{{ old('country', $companyEntity->country) }}" required maxlength="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white uppercase">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Timezone <span class="text-red-500">*</span></label>
                    <select name="timezone" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" {{ old('timezone', $companyEntity->timezone) === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Currency Code <span class="text-red-500">*</span></label>
                    <input type="text" name="currency" value="{{ old('currency', $companyEntity->currency) }}" required maxlength="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white uppercase">
                </div>
            </div>
        </div>

        <!-- Operations Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Operations & Calendar</h3>
                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">Define working hours and fiscal cycles.</p>
            </div>
            
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Fiscal Year Start</label>
                    <input type="text" name="fiscal_year_start" value="{{ old('fiscal_year_start', $companyEntity->fiscal_year_start) }}" placeholder="MM-DD" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Work Week Start <span class="text-red-500">*</span></label>
                    <select name="work_week_start" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="monday" {{ old('work_week_start', $companyEntity->work_week_start) === 'monday' ? 'selected' : '' }}>Monday</option>
                        <option value="sunday" {{ old('work_week_start', $companyEntity->work_week_start) === 'sunday' ? 'selected' : '' }}>Sunday</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 dark:text-slate-300">Working Days</label>
                    <div class="flex flex-wrap gap-4">
                        @php $savedDays = old('working_days', $companyEntity->working_days ?? []); @endphp
                        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="working_days[]" value="{{ $day }}" 
                                       class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-brand-500"
                                       {{ in_array($day, $savedDays) ? 'checked' : '' }}>
                                <span class="ml-2 text-sm text-slate-700 dark:text-slate-300">{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="md:col-span-2 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_active" value="1" 
                               class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500"
                               {{ old('is_active', $companyEntity->is_active) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Entity is Active</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200/80 dark:border-slate-700">
            <a href="{{ route('company-entities.index') }}" class="inline-flex justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700 transition">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 transition">
                Update Entity
            </button>
        </div>
    </form>
</div>
@endsection
