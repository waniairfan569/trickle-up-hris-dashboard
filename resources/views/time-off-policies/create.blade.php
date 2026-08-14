@extends('layouts.hr-app')

@section('title', 'Create Time-Off Policy')
@section('breadcrumb', 'Create Policy')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="policyForm()">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Create Time-Off Policy</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Configure allowance, accrual, and approval rules for a new leave type.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('time-off-policies.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
                Back to Policies
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('time-off-policies.store') }}" method="POST" class="space-y-6">
        @csrf

        <!-- Basic Info Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Basic Information</h3>
            </div>
            <div class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Policy Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Annual Leave (UK)" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Policy Type <span class="text-red-500">*</span></label>
                        <select name="type" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="annual" {{ old('type') == 'annual' ? 'selected' : '' }}>Annual Leave</option>
                            <option value="sick" {{ old('type') == 'sick' ? 'selected' : '' }}>Sick Leave</option>
                            <option value="unpaid" {{ old('type') == 'unpaid' ? 'selected' : '' }}>Unpaid Leave</option>
                            <option value="maternity" {{ old('type') == 'maternity' ? 'selected' : '' }}>Maternity</option>
                            <option value="paternity" {{ old('type') == 'paternity' ? 'selected' : '' }}>Paternity</option>
                            <option value="bereavement" {{ old('type') == 'bereavement' ? 'selected' : '' }}>Bereavement</option>
                            <option value="custom" {{ old('type') == 'custom' ? 'selected' : '' }}>Custom</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Description</label>
                        <textarea name="description" rows="2" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Allowance Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Allowance & Accrual</h3>
            </div>
            <div class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Days per Year <span class="text-red-500">*</span></label>
                        <input type="number" step="0.5" name="days_per_year" x-model="daysPerYear" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Accrual Method <span class="text-red-500">*</span></label>
                        <select name="accrual_type" x-model="accrualType" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="none">Granted completely on Jan 1st / Start Date</option>
                            <option value="monthly">Accrued Monthly</option>
                            <option value="annually">Accrued Annually</option>
                        </select>
                        <p x-show="accrualType === 'monthly'" class="mt-2 text-xs font-bold text-brand-600 dark:text-brand-400" x-text="`≈ ${Math.round((daysPerYear / 12) * 10) / 10} days accrued per month`" style="display: none;"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carry Over Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Year-End Carry Over</h3>
                    <p class="text-sm text-slate-500 mt-1">Allow unused balances to be moved to the next year.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="carry_over" value="1" x-model="carryOver" class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-300 dark:peer-focus:ring-brand-800 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-slate-600 peer-checked:bg-brand-600"></div>
                </label>
            </div>
            <div class="p-8" x-show="carryOver" style="display: none;">
                <div class="w-full md:w-1/2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Maximum Carry Over Days</label>
                    <input type="number" step="0.5" name="carry_over_max" value="{{ old('carry_over_max') }}" placeholder="Leave blank for unlimited" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>
        </div>

        <!-- Approval & Rules Section -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
            <div class="px-8 py-5 border-b border-slate-100 bg-slate-50/50 dark:border-slate-700/60 dark:bg-slate-800/50">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Approval & Request Rules</h3>
            </div>
            <div class="p-8 space-y-6">
                
                <div class="pb-6 border-b border-slate-100 dark:border-slate-700/60">
                    <label class="flex items-start mb-4">
                        <div class="flex items-center h-5">
                            <input type="checkbox" name="requires_approval" value="1" x-model="requiresApproval" class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        </div>
                        <div class="ml-3 text-sm">
                            <span class="font-bold text-slate-700 dark:text-slate-300">Requires Approval</span>
                            <p class="text-slate-500 text-xs mt-0.5">If unchecked, requests are auto-approved.</p>
                        </div>
                    </label>

                    <div x-show="requiresApproval" class="pl-8 pt-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Who must approve?</label>
                        <select name="approval_type" class="w-full md:w-1/2 rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                            <option value="manager" {{ old('approval_type') == 'manager' ? 'selected' : '' }}>Direct Manager Only</option>
                            <option value="hr_admin" {{ old('approval_type') == 'hr_admin' ? 'selected' : '' }}>HR Admin Only</option>
                            <option value="super_admin" {{ old('approval_type') == 'super_admin' ? 'selected' : '' }}>Super Admin Only</option>
                            <option value="both" {{ old('approval_type') == 'both' ? 'selected' : '' }}>Manager then HR Admin</option>
                            <option value="manager_super" {{ old('approval_type') == 'manager_super' ? 'selected' : '' }}>Manager then Super Admin</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Minimum Notice (Days)</label>
                        <input type="number" name="min_notice_days" value="{{ old('min_notice_days', 0) }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                    </div>
                </div>

                <div class="pt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_half_days" value="1" checked class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Allow Half-Day Requests</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="allow_negative_balance" value="1" class="rounded border-slate-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-900">
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Allow Negative Balances</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_paid" value="1" checked class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500">
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">This is Paid Leave</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500">
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Policy is Active</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="auto_assign_to_new_employees" value="1" checked class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500">
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Auto-assign to new employees</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="show_on_dashboard" value="1" {{ old('show_on_dashboard', true) ? 'checked' : '' }} class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500">
                        <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Show on dashboard balances</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-8">
            <a href="{{ route('time-off-policies.index') }}" class="inline-flex justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                Create Policy
            </button>
        </div>
    </form>
</div>

<script>
    function policyForm() {
        return {
            daysPerYear: {{ old('days_per_year', 20) }},
            accrualType: '{{ old('accrual_type', 'none') }}',
            carryOver: {{ old('carry_over') ? 'true' : 'false' }},
            requiresApproval: {{ old('requires_approval', true) ? 'true' : 'false' }}
        }
    }
</script>
@endsection
