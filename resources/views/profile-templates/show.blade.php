@extends('layouts.hr-app')

@section('title', 'Template Details: ' . $profile_template->name)
@section('breadcrumb', 'Template Details')

@section('content')
<div class="space-y-8 max-w-7xl mx-auto">
    <!-- Back Navigation & Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-5 dark:border-slate-800">
        <div class="flex items-center gap-x-3">
            <a href="{{ route('profile-templates.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-650 hover:bg-slate-50 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-750">
                <i data-lucide="arrow-left" class="h-5 w-5"></i>
            </a>
            <div>
                <div class="flex items-center gap-x-2">
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $profile_template->name }}</h1>
                    @if($profile_template->type === 'default')
                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-950">Default</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-650 border border-slate-200/60 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-800">Dynamic</span>
                    @endif

                    @if($profile_template->is_active)
                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-950">Active</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-100 dark:bg-rose-500/10 dark:text-rose-450 dark:border-rose-950">Inactive</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-1 dark:text-slate-400">{{ $profile_template->description ?? 'No description provided.' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($profile_template->type !== 'default')
                <a href="{{ route('profile-templates.edit', $profile_template->id) }}" class="inline-flex items-center gap-x-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                    <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                    <span>Edit Info</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Feedback Alerts -->
    @if(session('success'))
        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-emerald-800 text-sm dark:bg-emerald-950/20 dark:border-emerald-800 dark:text-emerald-400 flex items-center gap-2">
            <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

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

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <!-- Main Structure Details Panel -->
        <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Profile Sections & Fields</h2>
                <span class="text-xs text-slate-400 dark:text-slate-500">{{ $profile_template->sections->count() }} Sections &bull; {{ $profile_template->sections->flatMap->fields->count() }} Total Fields</span>
            </div>

            <!-- Sections List -->
            @forelse($profile_template->sections as $section)
                @php
                    $isDefaultTemplate = $profile_template->type === 'default';
                @endphp
                <div class="section-accordion-card group/card bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm dark:bg-slate-800 dark:border-slate-850 transition duration-150">
                    
                    <!-- Section Header -->
                    <div class="flex items-center justify-between cursor-pointer select-none" onclick="toggleAccordion('{{ $section->id }}')">
                        <div class="flex items-center gap-x-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 border border-slate-200/50 text-slate-650 dark:bg-slate-850 dark:border-slate-750 dark:text-slate-455">
                                <i data-lucide="{{ $section->icon }}" class="h-4.5 w-4.5"></i>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white group-hover/card:text-brand-600 transition dark:group-hover/card:text-brand-450">{{ $section->name }}</h3>
                                <p class="text-[10px] text-slate-400 mt-0.5 dark:text-slate-500">{{ $section->fields->count() }} fields defined</p>
                            </div>
                        </div>

                        <!-- Actions & Chevron -->
                        <div class="flex items-center gap-x-3" onclick="event.stopPropagation()">
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="toggleSectionEdit('{{ $section->id }}')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-250 bg-white text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                                    <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                </button>
                                @if(!$isDefaultTemplate)
                                    <form action="{{ route('profile-sections.destroy', $section->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this profile section and all its dynamic custom fields?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-600 hover:bg-rose-100 transition">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                            
                            <!-- Expand/Collapse Chevron -->
                            <button type="button" onclick="toggleAccordion('{{ $section->id }}')" class="section-chevron-btn-{{ $section->id }} text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                                <i data-lucide="chevron-down" class="section-chevron-icon-{{ $section->id }} h-5 w-5 transform transition-transform duration-200"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Inline Section Edit Form -->
                    <div id="section_edit_form_{{ $section->id }}" class="hidden mt-4 p-4 border border-slate-200/50 rounded-xl bg-slate-50/50 dark:bg-slate-850 dark:border-slate-750">
                        <form action="{{ route('profile-sections.update', $section->id) }}" method="POST" class="space-y-4">
                            @csrf
                            @method('PUT')
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Section Name <span class="text-rose-550">*</span></label>
                                    <input type="text" name="name" value="{{ $section->name }}" required class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-xs text-slate-950 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-350">Section Icon <span class="text-rose-550">*</span></label>
                                    <select name="icon" class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-xs text-slate-950 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white">
                                        <option value="user" {{ $section->icon == 'user' ? 'selected' : '' }}>User Profile (user)</option>
                                        <option value="briefcase" {{ $section->icon == 'briefcase' ? 'selected' : '' }}>Employment (briefcase)</option>
                                        <option value="shield" {{ $section->icon == 'shield' ? 'selected' : '' }}>Security & Auth (shield)</option>
                                        <option value="map-pin" {{ $section->icon == 'map-pin' ? 'selected' : '' }}>Location (map-pin)</option>
                                        <option value="phone" {{ $section->icon == 'phone' ? 'selected' : '' }}>Contact Info (phone)</option>
                                        <option value="calendar" {{ $section->icon == 'calendar' ? 'selected' : '' }}>Important Dates (calendar)</option>
                                        <option value="file-text" {{ $section->icon == 'file-text' ? 'selected' : '' }}>Documents (file-text)</option>
                                        <option value="award" {{ $section->icon == 'award' ? 'selected' : '' }}>Qualifications (award)</option>
                                        <option value="heart" {{ $section->icon == 'heart' ? 'selected' : '' }}>Emergency / Health (heart)</option>
                                        <option value="users" {{ $section->icon == 'users' ? 'selected' : '' }}>Social / Team (users)</option>
                                        <option value="globe" {{ $section->icon == 'globe' ? 'selected' : '' }}>Web/Identity (globe)</option>
                                        <option value="activity" {{ $section->icon == 'activity' ? 'selected' : '' }}>Health / Fitness (activity)</option>
                                        <option value="database" {{ $section->icon == 'database' ? 'selected' : '' }}>Database Custom (database)</option>
                                        <option value="key" {{ $section->icon == 'key' ? 'selected' : '' }}>Permissions (key)</option>
                                        <option value="link" {{ $section->icon == 'link' ? 'selected' : '' }}>Social URL (link)</option>
                                        <option value="mail" {{ $section->icon == 'mail' ? 'selected' : '' }}>Email Communication (mail)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end gap-x-2 border-t border-slate-100 pt-3 dark:border-slate-700/60">
                                <button type="button" onclick="toggleSectionEdit('{{ $section->id }}')" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 transition">Cancel</button>
                                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-slate-900 hover:bg-brand-700 transition">Save Changes</button>
                            </div>
                        </form>
                    </div>

                    <!-- Collapsible Card Body -->
                    <div id="section_body_{{ $section->id }}" class="mt-5 space-y-4">
                        
                        <!-- Table of Fields -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-750">
                                <thead>
                                    <tr class="bg-slate-50/70 text-left dark:bg-slate-850">
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Label / Name</th>
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Key</th>
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Type</th>
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Visibility</th>
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Required</th>
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Emp Edit</th>
                                        <th scope="col" class="py-2.5 px-3 text-[10px] font-bold uppercase tracking-wider text-slate-450">Encryption</th>
                                        <th scope="col" class="relative py-2.5 px-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-455">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-105/60 dark:divide-slate-750/70">
                                    @forelse($section->fields as $field)
                                        <tr class="hover:bg-slate-50/30 transition dark:hover:bg-slate-850/40">
                                            <td class="whitespace-nowrap py-3 px-3">
                                                <div class="flex items-center gap-x-1.5">
                                                    <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $field->name }}</span>
                                                    @if($field->is_system)
                                                        <i data-lucide="shield" class="h-3 w-3 text-slate-400" title="System Field"></i>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap py-3 px-3 text-[10px] font-mono text-slate-500 dark:text-slate-400">{{ $field->key }}</td>
                                            <td class="whitespace-nowrap py-3 px-3">
                                                <span class="inline-flex items-center rounded-md bg-slate-50 px-1.5 py-0.5 text-[9px] font-bold text-slate-650 border border-slate-200/60 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-800">
                                                    {{ str_replace('_', ' ', $field->type) }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap py-3 px-3">
                                                @switch($field->visibility)
                                                    @case('public')
                                                        <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-950">Public</span>
                                                        @break
                                                    @case('internal')
                                                        <span class="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[9px] font-bold text-blue-700 border border-blue-100 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-950">Internal</span>
                                                        @break
                                                    @case('private')
                                                        <span class="inline-flex items-center rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-700 border border-amber-100 dark:bg-amber-500/10 dark:text-amber-450 dark:border-amber-950">Private</span>
                                                        @break
                                                    @case('manager')
                                                        <span class="inline-flex items-center rounded bg-purple-50 px-1.5 py-0.5 text-[9px] font-bold text-purple-700 border border-purple-100 dark:bg-purple-500/10 dark:text-purple-400 dark:border-purple-950">Manager Only</span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td class="whitespace-nowrap py-3 px-3 text-center">
                                                @if($field->is_required)
                                                    <span class="text-rose-500 font-bold text-xs" title="Required">&check;</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600 text-xs">-</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap py-3 px-3 text-center">
                                                @if($field->employee_can_edit)
                                                    <span class="text-emerald-500 font-bold text-xs" title="Yes">&check;</span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600 font-bold text-xs">&times;</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap py-3 px-3">
                                                @if($field->is_encrypted)
                                                    <span class="inline-flex items-center gap-0.5 rounded bg-amber-100 px-1 py-0.5 text-[9px] font-bold text-amber-800 dark:bg-amber-950/20 dark:text-amber-400 border border-amber-200/50">
                                                        <i data-lucide="lock" class="h-2.5 w-2.5"></i> AES-256
                                                    </span>
                                                @else
                                                    <span class="text-slate-300 dark:text-slate-600 text-xs italic">-</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap py-3 px-3 text-right text-xs">
                                                @if($field->is_system)
                                                    <span class="text-[10px] text-slate-400 italic dark:text-slate-500">System Locked</span>
                                                @else
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <button type="button" onclick="toggleFieldEdit('{{ $field->id }}')" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                                                            <i data-lucide="edit-3" class="h-3 w-3"></i>
                                                        </button>
                                                        <form action="{{ route('profile-fields.destroy', $field->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this custom field? This will erase all employee data saved under this field.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-600 hover:bg-rose-100 transition">
                                                                <i data-lucide="trash-2" class="h-3 w-3"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>

                                        <!-- Inline Field Edit Form -->
                                        @if(!$field->is_system)
                                            <tr id="field_edit_wrapper_{{ $field->id }}" class="inline-field-edit-form-wrapper hidden bg-slate-50/50 dark:bg-slate-900/10">
                                                <td colspan="8" class="p-4">
                                                    @include('profile-templates.partials.field-form', ['section' => $section, 'field' => $field])
                                                </td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-6 text-center text-xs text-slate-400 italic dark:text-slate-500">
                                                No fields defined in this section yet. Click Add Field to get started.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Field Button -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-750">
                            <button type="button" onclick="toggleFieldAdd('{{ $section->id }}')" class="inline-flex items-center gap-x-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-350 dark:hover:bg-slate-750">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                <span>Add field to {{ $section->name }}</span>
                            </button>
                        </div>

                        <!-- Inline Add Field Form Wrapper -->
                        <div id="field_add_wrapper_{{ $section->id }}" class="inline-field-form-wrapper hidden mt-4">
                            @include('profile-templates.partials.field-form', ['section' => $section, 'field' => null])
                        </div>

                    </div>
                </div>
            @empty
                <div class="bg-white border border-slate-200/80 rounded-2xl p-12 text-center shadow-sm dark:bg-slate-800 dark:border-slate-850 space-y-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mx-auto dark:bg-slate-900 dark:text-slate-650">
                        <i data-lucide="layers" class="h-6 w-6"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">No Sections Configured</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">This template has no custom sections. Create a section below to start building dynamic profile fields.</p>
                </div>
            @endforelse

            <!-- Add Section Panel (Visible only on Dynamic templates) -->
            @if($profile_template->type !== 'default')
                <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm dark:bg-slate-800 dark:border-slate-850">
                    <div class="flex items-center gap-x-2 border-b border-slate-100 pb-3 mb-5 dark:border-slate-700/60">
                        <i data-lucide="plus-circle" class="h-5 w-5 text-brand-600"></i>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Create New Section</h3>
                    </div>

                    <form action="{{ route('profile-templates.sections.store', $profile_template->id) }}" method="POST" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label for="section_name" class="block text-xs font-bold text-slate-700 dark:text-slate-350">Section Name <span class="text-rose-550">*</span></label>
                                <input type="text" name="name" id="section_name" required placeholder="e.g., Visas & Work Permits, Skills & Assets"
                                    class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 placeholder-slate-400 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
                            </div>

                            <div class="space-y-2">
                                <label for="section_icon" class="block text-xs font-bold text-slate-700 dark:text-slate-350">Tabler / Lucide Icon Name <span class="text-rose-550">*</span></label>
                                <select name="icon" id="section_icon" required
                                    class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-950 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
                                    <option value="user">User Profile (user)</option>
                                    <option value="briefcase">Employment info (briefcase)</option>
                                    <option value="shield">Security & Settings (shield)</option>
                                    <option value="map-pin">Locations & Travel (map-pin)</option>
                                    <option value="phone">Contact Details (phone)</option>
                                    <option value="calendar">Important Milestones (calendar)</option>
                                    <option value="file-text">Contracts & Docs (file-text)</option>
                                    <option value="award">Achievements & Awards (award)</option>
                                    <option value="heart">Emergency contacts (heart)</option>
                                    <option value="users">Teams & Departments (users)</option>
                                    <option value="globe">Social Networks / Websites (globe)</option>
                                    <option value="activity">Medical / Health metrics (activity)</option>
                                    <option value="database">System Fields (database)</option>
                                    <option value="key">API Keys / Tokens (key)</option>
                                    <option value="link">Custom URL (link)</option>
                                    <option value="mail">Corporate Email (mail)</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end border-t border-slate-100 pt-4 dark:border-slate-750">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-4 py-2.5 text-xs font-bold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 hover:shadow-lg transition duration-150">
                                Create Section
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <!-- Sidebar Assignment & Metrics Panel -->
        <div class="space-y-6">
            @php
                $assignedCount = $isDefaultTemplate ? \App\Models\Employee::count() : $profile_template->employees->count();
                $assignedUserIds = $profile_template->employees->pluck('id')->toArray();
                $unassignedUsers = \App\Models\User::whereNotIn('id', $assignedUserIds)->with('department')->get();
            @endphp
            
            <!-- General Stats Card -->
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm dark:bg-slate-800 dark:border-slate-850 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Template Specifications</h3>
                
                <div class="space-y-3.5 text-xs">
                    <div class="flex items-center justify-between border-b border-slate-50 pb-2 dark:border-slate-750">
                        <span class="text-slate-400">Type Classification</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200 capitalize">{{ $profile_template->type }} template</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-50 pb-2 dark:border-slate-750">
                        <span class="text-slate-400">Active Status</span>
                        <span class="font-bold {{ $profile_template->is_active ? 'text-emerald-600' : 'text-slate-500' }}">{{ $profile_template->is_active ? 'Online & Deployable' : 'Offline / Standard' }}</span>
                    </div>
                    <div class="flex items-center justify-between border-b border-slate-50 pb-2 dark:border-slate-750">
                        <span class="text-slate-400">{{ $isDefaultTemplate ? 'Applies To' : 'Total Assigned Staff' }}</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ $isDefaultTemplate ? 'All (' . $assignedCount . ')' : $assignedCount . ' employees' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400">Unique Code Key</span>
                        <span class="font-mono text-slate-600 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 text-[10px] dark:bg-slate-850 dark:border-slate-750 dark:text-slate-400">{{ $profile_template->slug }}</span>
                    </div>
                </div>
            </div>

            <!-- Employee Assignments (dynamic templates only) -->
            @if($isDefaultTemplate)
            <div class="bg-white border border-blue-200/80 rounded-2xl p-5 shadow-sm dark:bg-slate-800 dark:border-blue-900/40 space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400">Global Template</h3>
                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    This is the <strong>default</strong> template — it applies to <strong>every employee automatically</strong>. There is no individual assignment; everyone in the directory inherits these sections and fields.
                </p>
                <div class="rounded-xl bg-blue-50 border border-blue-100 px-3 py-2 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:border-blue-900/30 dark:text-blue-300">
                    Applies to all {{ $assignedCount }} employees
                </div>
            </div>
            @else
            <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm dark:bg-slate-800 dark:border-slate-850 space-y-5">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Employee Assignments</h3>
                    <p class="text-[10px] text-slate-450 dark:text-slate-500 leading-normal mt-0.5">Control which employees utilize this customized profile structure.</p>
                </div>

                <!-- Assignment Form & Search input -->
                @if($profile_template->is_active)
                    <div class="space-y-4">
                        <div class="relative">
                            <input type="text" id="employee-search" placeholder="Type name to assign employee..." 
                                class="block w-full rounded-xl border border-slate-200 px-4 py-2 text-xs text-slate-950 placeholder-slate-450 focus:border-brand-500 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-850 dark:text-white focus:outline-none focus:ring-2 focus:ring-offset-0 transition duration-150">
                            <span class="absolute right-3.5 top-2.5 text-slate-400">
                                <i data-lucide="search" class="h-4 w-4"></i>
                            </span>
                        </div>

                        <!-- In-browser Search Results -->
                        <div id="search-results" class="max-h-[220px] overflow-y-auto border border-slate-150 rounded-xl divide-y divide-slate-100 bg-slate-50/50 hidden dark:border-slate-750 dark:divide-slate-750 dark:bg-slate-900/10">
                            @forelse($unassignedUsers as $user)
                                <div class="employee-search-item flex items-center justify-between p-2.5" data-name="{{ strtolower($user->first_name . ' ' . $user->last_name) }}">
                                    <div class="flex items-center gap-x-2 min-w-0">
                                        <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-350">
                                            {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0 truncate">
                                            <span class="block text-[11px] font-bold text-slate-800 dark:text-slate-200 truncate">{{ $user->first_name }} {{ $user->last_name }}</span>
                                            <span class="block text-[9px] text-slate-400 truncate">{{ $user->department?->name ?? 'No Department' }}</span>
                                        </div>
                                    </div>
                                    <form action="{{ route('profile-templates.assign') }}" method="POST" class="flex-shrink-0">
                                        @csrf
                                        <input type="hidden" name="template_id" value="{{ $profile_template->id }}">
                                        <input type="hidden" name="user_ids[]" value="{{ $user->id }}">
                                        <button type="submit" class="inline-flex h-7 px-2.5 items-center justify-center rounded-lg bg-brand-600 text-[10px] font-bold text-slate-900 hover:bg-brand-700 transition">
                                            Assign
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="p-3 text-center text-xs text-slate-450 italic dark:text-slate-500">
                                    All active employees have already been assigned.
                                </div>
                            @endforelse
                            <div id="no-search-results" class="p-3 text-center text-[11px] text-slate-400 dark:text-slate-500 hidden">
                                No unassigned employees match "<span id="search-query-text"></span>".
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-slate-150 bg-slate-50/50 p-3 text-center text-xs text-slate-450 dark:border-slate-750 dark:bg-slate-900/10">
                        Template must be Active to assign new employees.
                    </div>
                @endif

                <!-- Currently Assigned Staff list -->
                <div class="space-y-3 pt-3 border-t border-slate-100 dark:border-slate-750">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Assigned Employees ({{ $assignedCount }})</span>
                    
                    <div class="space-y-2.5 max-h-[350px] overflow-y-auto pr-1">
                        @forelse($profile_template->employees as $employee)
                            <div class="flex items-center justify-between rounded-xl border border-slate-100 p-2.5 hover:border-slate-200 transition dark:border-slate-750 dark:hover:border-slate-700">
                                <div class="flex items-center gap-x-2 min-w-0">
                                    <div class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-indigo-50 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/20 dark:text-indigo-400">
                                        {{ strtoupper(substr($employee->first_name, 0, 1) . substr($employee->last_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 truncate">
                                        <a href="{{ route('employees.profile', $employee->id) }}" class="block text-[11px] font-bold text-slate-800 hover:text-brand-600 transition dark:text-slate-200 dark:hover:text-brand-450 truncate">
                                            {{ $employee->first_name }} {{ $employee->last_name }}
                                        </a>
                                        <span class="block text-[9px] text-slate-450 truncate">{{ $employee->job_title ?? 'Staff Member' }}</span>
                                    </div>
                                </div>

                                @if($profile_template->type !== 'default')
                                    <form action="{{ route('profile-templates.unassign') }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this employee from this dynamic template? Custom field values will not be lost, but will be hidden until reassigned.');">
                                        @csrf
                                        <input type="hidden" name="template_id" value="{{ $profile_template->id }}">
                                        <input type="hidden" name="user_id" value="{{ $employee->id }}">
                                        <button type="submit" class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-rose-100 bg-rose-50 text-rose-600 hover:bg-rose-100 transition dark:bg-rose-950/20 dark:border-rose-900/30 dark:text-rose-400">
                                            &times;
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-6 text-[11px] text-slate-450 italic dark:text-slate-500">
                                No employees explicitly assigned yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

<script>
// Expand/Collapse Section Accordions
function toggleAccordion(sectionId) {
    const body = document.getElementById('section_body_' + sectionId);
    const chevronIcon = document.querySelector('.section-chevron-icon-' + sectionId);
    
    if (body && chevronIcon) {
        body.classList.toggle('hidden');
        if (body.classList.contains('hidden')) {
            chevronIcon.style.transform = 'rotate(0deg)';
        } else {
            chevronIcon.style.transform = 'rotate(180deg)';
        }
    }
}

// Toggle Inline Section Edit Form
function toggleSectionEdit(sectionId) {
    const editForm = document.getElementById('section_edit_form_' + sectionId);
    if (editForm) {
        editForm.classList.toggle('hidden');
        if(!editForm.classList.contains('hidden')){
            // Expand parent body if closed
            const body = document.getElementById('section_body_' + sectionId);
            if (body && body.classList.contains('hidden')) {
                toggleAccordion(sectionId);
            }
        }
    }
}

// Toggle Inline Add Field Form
function toggleFieldAdd(sectionId) {
    const wrapper = document.getElementById('field_add_wrapper_' + sectionId);
    if (wrapper) {
        wrapper.classList.toggle('hidden');
        if (!wrapper.classList.contains('hidden')) {
            // Scroll into view gently
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

// Toggle Inline Edit Field Form
function toggleFieldEdit(fieldId) {
    const row = document.getElementById('field_edit_wrapper_' + fieldId);
    if (row) {
        row.classList.toggle('hidden');
    }
}

// Employee In-Browser Search Logic
(function() {
    const searchInput = document.getElementById('employee-search');
    const searchResults = document.getElementById('search-results');
    const searchItems = document.querySelectorAll('.employee-search-item');
    const noResults = document.getElementById('no-search-results');
    const queryText = document.getElementById('search-query-text');

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function() {
            const query = searchInput.value.trim().toLowerCase();
            if (!query) {
                searchResults.classList.add('hidden');
                return;
            }

            searchResults.classList.remove('hidden');
            let hasMatches = false;

            searchItems.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(query)) {
                    item.classList.remove('hidden');
                    hasMatches = true;
                } else {
                     item.classList.add('hidden');
                }
            });

            if (hasMatches) {
                noResults.classList.add('hidden');
            } else {
                noResults.classList.remove('hidden');
                queryText.textContent = searchInput.value.trim();
            }
        });

        // Close search lists on clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#employee-search') && !e.target.closest('#search-results')) {
                searchResults.classList.add('hidden');
            }
        });
    }
})();
</script>
@endsection
