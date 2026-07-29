@extends('layouts.hr-app')

@section('title', 'Add Department')
@section('breadcrumb', 'Add Department')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200/80 pb-5 dark:border-slate-700/60">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Create Department</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Add a new division or team to your organizational structure.
            </p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('departments.index') }}" class="text-sm font-bold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
                Back to List
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 p-4 border border-red-200 dark:bg-red-500/10 dark:border-red-500/20">
            <div class="flex">
                <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                <div class="ml-3">
                    <ul class="list-disc pl-5 text-sm text-red-700 space-y-1 dark:text-red-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('departments.store') }}" method="POST" class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden dark:bg-slate-800 dark:border-slate-700/80">
        @csrf
        <div class="p-8 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Department Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Parent Department</label>
                    <select name="parent_id" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="">None (Top Level)</option>
                        @foreach($topLevelDepartments as $parent)
                            <option value="{{ $parent->id }}" {{ (int) old('parent_id', $preselectParent ?? null) === $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Description</label>
                    <textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Department Head</label>
                    <select name="head_user_id" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                        <option value="">-- Unassigned --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('head_user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Theme Color</label>
                    <div class="flex flex-wrap gap-3" x-data="{ selectedColor: '{{ old('color', '#3B82F6') }}' }">
                        <input type="hidden" name="color" x-model="selectedColor">
                        @foreach(['#ef4444', '#f97316', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'] as $c)
                            <button type="button" @click="selectedColor = '{{ $c }}'" 
                                    class="h-8 w-8 rounded-full border-2 transition-transform hover:scale-110 focus:outline-none flex items-center justify-center"
                                    :class="selectedColor === '{{ $c }}' ? 'border-slate-400 dark:border-slate-300 scale-110' : 'border-transparent'"
                                    style="background-color: {{ $c }}">
                                <i data-lucide="check" class="h-4 w-4 text-white" x-show="selectedColor === '{{ $c }}'"></i>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 dark:text-slate-300">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" class="w-full rounded-xl border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-slate-900 dark:border-slate-600 dark:text-white">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="is_active" value="1" 
                           class="rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-900 dark:checked:bg-emerald-500"
                           {{ old('is_active', true) ? 'checked' : '' }}>
                    <span class="ml-2 text-sm font-bold text-slate-700 dark:text-slate-300">Department is Active</span>
                </label>
            </div>
        </div>
        
        <div class="bg-slate-50 px-8 py-5 border-t border-slate-100 dark:bg-slate-800/50 dark:border-slate-700/60 flex justify-end gap-3">
            <a href="{{ route('departments.index') }}" class="inline-flex justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm border border-slate-300 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 transition">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-bold text-slate-900 shadow-md hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 transition">
                Create Department
            </button>
        </div>
    </form>
</div>
@endsection
