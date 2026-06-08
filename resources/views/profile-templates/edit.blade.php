@extends('layouts.hr-app')

@section('title', 'Edit Template Info')
@section('breadcrumb', 'Edit Profile Template')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-x-3">
        <a href="{{ route('profile-templates.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-650 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-750">
            <i data-lucide="arrow-left" class="h-5 w-5"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">Edit Profile Template</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Update general details of this layout structure.</p>
        </div>
    </div>

    <!-- Errors -->
    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-rose-800 text-xs dark:bg-rose-950/20 dark:border-rose-900 dark:text-rose-450 space-y-1">
            <div class="flex items-center gap-2 font-bold mb-1">
                <i data-lucide="alert-circle" class="h-4.5 w-4.5 text-rose-500"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm dark:bg-slate-800 dark:border-slate-850">
        <form action="{{ route('profile-templates.update', $profile_template->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            
            <div class="space-y-2">
                <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-350">Template Name <span class="text-rose-550">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $profile_template->name) }}" placeholder="e.g., Engineer Profile Template, Contractor Form" required
                    class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 placeholder-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
            </div>

            <div class="space-y-2">
                <label for="description" class="block text-xs font-bold text-slate-700 dark:text-slate-350">Description</label>
                <textarea name="description" id="description" rows="4" placeholder="Briefly describe what department, role, or region this profile layout serves..."
                    class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 placeholder-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">{{ old('description', $profile_template->description) }}</textarea>
            </div>

            <div class="flex items-center gap-x-3">
                <div class="flex h-5 items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $profile_template->is_active) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850">
                </div>
                <div class="text-sm">
                    <label for="is_active" class="font-bold text-slate-700 dark:text-slate-350 text-xs">Active & Deployable</label>
                    <p class="text-slate-400 dark:text-slate-500 text-xxs">Allow assigning this template to employees right away.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-x-3 border-t border-slate-100 pt-5 dark:border-slate-750">
                <a href="{{ route('profile-templates.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 hover:shadow-lg transition duration-150">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
