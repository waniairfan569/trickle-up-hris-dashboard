@extends('layouts.hr-app')

@section('title', 'Employee Directory')
@section('breadcrumb', 'Employees')

@section('content')
@php
    $auth = auth()->user();
@endphp

<div class="space-y-6">

    <!-- Directory Header & Action -->
    <div class="space-y-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Employee Directory</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Browse, filter, and view employee profiles according to your organizational scope.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('org-chart') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                <i data-lucide="network" class="h-4 w-4"></i>
                <span>Org Chart</span>
            </a>
            <a href="{{ route('files.index') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                <i data-lucide="folder" class="h-4 w-4"></i>
                <span>Files</span>
            </a>
            <a href="{{ route('work-calendar') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                <i data-lucide="calendar-days" class="h-4 w-4"></i>
                <span>Work Calendar</span>
            </a>
            @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('hr_admin'))
                <button type="button" @click="$dispatch('open-import-modal')" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="upload" class="h-4 w-4"></i>
                    <span>Import</span>
                </button>
                <a href="{{ route('employees.export') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    <span>Export</span>
                </a>
                <a href="{{ route('employees.archived') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="archive" class="h-4 w-4"></i>
                    <span>Archived</span>
                </a>
                <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-white border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition duration-150 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                    <span>Reports</span>
                </a>
                <a href="{{ route('employees.create') }}" class="inline-flex items-center gap-x-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-slate-900 shadow-md shadow-brand-500/20 hover:bg-brand-700 transition duration-150">
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    <span>Add New Employee</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 dark:bg-slate-800 dark:border-slate-700">
        <form method="GET" action="{{ route('employees.index') }}" class="grid grid-cols-1 md:grid-cols-12 gap-4">

            <!-- Search -->
            <div class="md:col-span-6">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Search Employee</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <i data-lucide="search" class="h-4 w-4 text-slate-400 dark:text-slate-500"></i>
                    </div>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="Search name, job title..." 
                           class="w-full text-xs font-semibold border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 bg-slate-50/50 text-slate-850 placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
            </div>

            {{-- Entity & Location filters — super admin only. --}}
            @if($auth->isSuperAdmin())
            <!-- Entity Filter -->
            <div class="md:col-span-3">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Entity</label>
                <select name="company_entity_id" onchange="this.form.submit()"
                        class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">All Entities</option>
                    @foreach($entities as $ent)
                        <option value="{{ $ent->id }}" {{ request('company_entity_id') == $ent->id ? 'selected' : '' }}>{{ $ent->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Location Filter -->
            <div class="md:col-span-3">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Location</label>
                <select name="job_location_id" onchange="this.form.submit()"
                        class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">All Locations</option>
                    @foreach($jobLocations as $loc)
                        <option value="{{ $loc->id }}" {{ request('job_location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Department Filter -->
            <div class="md:col-span-4">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Department</label>
                <select name="department" onchange="this.form.submit()"
                        class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Role Filter (admins/HR only) -->
            @if($auth->isAdmin())
            <div class="md:col-span-4">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Role Permission</label>
                <select name="role" onchange="this.form.submit()"
                        class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="">All Roles</option>
                    @foreach($rolesMap as $slug => $name)
                        <option value="{{ $slug }}" {{ request('role') == $slug ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Sort -->
            @php $sort = request('sort', 'name_asc'); @endphp
            <div class="md:col-span-4">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Sort By</label>
                <select name="sort" onchange="this.form.submit()"
                        class="w-full text-xs font-semibold border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50/50 text-slate-800 focus:border-brand-500 focus:bg-white focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    <option value="name_asc"   {{ $sort === 'name_asc'   ? 'selected' : '' }}>Name (A–Z)</option>
                    <option value="name_desc"  {{ $sort === 'name_desc'  ? 'selected' : '' }}>Name (Z–A)</option>
                    <option value="job_title"  {{ $sort === 'job_title'  ? 'selected' : '' }}>Job Title</option>
                    <option value="department" {{ $sort === 'department' ? 'selected' : '' }}>Department</option>
                    <option value="recent"     {{ $sort === 'recent'     ? 'selected' : '' }}>Recently Added</option>
                </select>
            </div>
            </div>
            
        </form>
    </div>

    <!-- Directory Sleek Table -->
    <div class="overflow-hidden rounded-2xl bg-white border border-slate-200/80 shadow-sm dark:bg-slate-800 dark:border-slate-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold uppercase tracking-wider text-slate-450 dark:bg-slate-900/40 dark:border-slate-700/60">
                        <th class="py-4 px-6">Name</th>
                        <th class="py-4 px-6">Department</th>
                        <th class="py-4 px-6">Manager</th>
                        @if($auth->isAdmin())
                        <th class="py-4 px-6">RBAC Role</th>
                        <th class="py-4 px-6">Attendance</th>
                        @endif
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 text-xs dark:divide-slate-700/60">
                    @forelse($employees as $emp)
                        @if(!$emp->user) @continue @endif
                        @php
                            // Display as "Last name, First name" (Workable style).
                            $fullName = $emp->user->last_name
                                ? trim($emp->user->last_name . ', ' . $emp->user->first_name)
                                : ($emp->user->full_name ?? 'Unknown');
                            // job_title lives in two places that drift: users.job_title (the source
                            // of truth the profile edits) and the denormalised employees.job_title
                            // copy, which is often the literal "Employee" placeholder. Normalise
                            // each (blank OR "Employee" => unset), then prefer the user's title.
                            $normTitle = function ($v) {
                                $v = trim((string) $v);
                                return ($v !== '' && strcasecmp($v, 'Employee') !== 0) ? $v : null;
                            };
                            $title = $normTitle($emp->user->job_title ?? null) ?? $normTitle($emp->job_title);
                            $deptName = $emp->department->name ?? 'Unassigned';
                            $roleSlug = $emp->user->role->slug ?? 'employee';

                            // Account lifecycle: Pending (invited, not yet accepted) → Active (accepted + logged in).
                            $acct = $emp->user->account_status ?? 'active';
                            $statusLabel = match($acct) {
                                'invited'     => 'Pending',
                                'active'      => 'Active',
                                'suspended'   => 'Suspended',
                                'deactivated' => 'Archived',
                                default       => ucfirst($acct),
                            };
                            $statusClasses = match($acct) {
                                'active'      => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
                                'invited'     => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                                'suspended'   => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
                                default       => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
                            };
                        @endphp
                        
                        <tr class="hover:bg-slate-50/40 dark:hover:bg-slate-750/30 transition">
                            
                            <!-- Name (clickable → profile) -->
                            <td class="py-4 px-6">
                                <a href="{{ route('employees.profile', $emp->user_id) }}" class="flex items-center space-x-3 group">
                                    @if($emp->user->avatar_url)
                                        <img src="{{ $emp->user->avatar_url }}" alt="{{ $fullName }}" class="h-9 w-9 shrink-0 rounded-xl object-cover ring-1 ring-slate-100 dark:ring-slate-750">
                                    @else
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-400 to-indigo-500 font-bold text-white shadow-sm shadow-indigo-500/10">
                                            {{ $emp->user->initials ?? 'EM' }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <span class="font-bold text-slate-900 dark:text-white block group-hover:text-brand-600 transition truncate">{{ $fullName }}</span>
                                        <span class="text-[10px] text-slate-400 block truncate">{{ $emp->user->email ?? $emp->email }}</span>
                                        @if($title)
                                            <span class="text-[11px] font-medium text-slate-600 dark:text-slate-300 block truncate">{{ $title }}</span>
                                        @else
                                            <span class="text-[11px] italic text-slate-400 block truncate">No job title set</span>
                                        @endif
                                        @if($emp->user->jobLocation)
                                            <span class="mt-1 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                                {{ $emp->user->jobLocation->is_remote ? '🏠' : '📍' }} {{ $emp->user->jobLocation->name }}
                                            </span>
                                        @endif
                                    </div>
                                </a>
                            </td>

                            <!-- Department -->
                            <td class="py-4 px-6">
                                @if($emp->department)
                                    @php
                                        $palette = ['#3B82F6','#10B981','#8B5CF6','#F59E0B','#EF4444','#06B6D4','#EC4899','#14B8A6','#6366F1','#F97316'];
                                        $dc = $emp->department->color ?: $palette[abs(crc32($emp->department->name)) % count($palette)];
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                          style="background-color: {{ $dc }}1a; color: {{ $dc }};">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background-color: {{ $dc }};"></span>
                                        {{ $emp->department->name }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">Unassigned</span>
                                @endif
                            </td>
                            
                            <!-- Manager -->
                            <td class="py-4 px-6">
                                @if($emp->user->manager)
                                    <a href="{{ route('employees.profile', $emp->user->manager_id) }}" class="inline-flex items-center gap-2 group">
                                        @if($emp->user->manager->avatar_url)
                                            <img src="{{ $emp->user->manager->avatar_url }}" alt="{{ $emp->user->manager->full_name }}" class="h-6 w-6 rounded-lg object-cover ring-1 ring-slate-100 dark:ring-slate-750">
                                        @else
                                            <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-[9px] font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ $emp->user->manager->initials ?? '–' }}</span>
                                        @endif
                                        <span class="font-semibold text-slate-700 group-hover:text-brand-600 transition dark:text-slate-300">{{ $emp->user->manager->full_name }}</span>
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            @if($auth->isAdmin())
                            <!-- Role Badge / super-admin role changer -->
                            <td class="py-4 px-6">
                                @if($auth->hasRole('super_admin') && $emp->user_id !== $auth->id)
                                    <form action="{{ route('employees.update-role', $emp->user_id) }}" method="POST" class="inline-flex">
                                        @csrf @method('PUT')
                                        <select name="role" onchange="this.form.submit()" title="Change role"
                                                class="rounded-lg border-slate-300 text-[11px] font-bold py-1 pl-2 pr-7 text-slate-700 shadow-sm focus:ring-brand-500 focus:border-brand-500 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200">
                                            @foreach($rolesMap as $slug => $name)
                                                <option value="{{ $slug }}" {{ (optional($emp->user->role)->slug ?? 'employee') === $slug ? 'selected' : '' }}>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <x-role-badge :role="$emp->user->role" />
                                @endif
                            </td>

                            <!-- Attendance mode (biometric / remote) -->
                            <td class="py-4 px-6">
                                @php $mode = $emp->user->attendance_mode ?? 'biometric'; @endphp
                                <form action="{{ route('employees.update-attendance-mode', $emp->user_id) }}" method="POST" class="inline-flex">
                                    @csrf @method('PUT')
                                    <select name="attendance_mode" onchange="this.form.submit()" title="Attendance mode"
                                            class="rounded-lg border-slate-300 text-[11px] font-bold py-1 pl-2 pr-7 shadow-sm focus:ring-brand-500 focus:border-brand-500 dark:bg-slate-700 dark:border-slate-600 {{ $mode === 'remote' ? 'text-emerald-700' : 'text-indigo-700' }}">
                                        <option value="biometric" {{ $mode === 'biometric' ? 'selected' : '' }}>On-site · Biometric</option>
                                        <option value="remote" {{ $mode === 'remote' ? 'selected' : '' }}>Remote · Dashboard</option>
                                    </select>
                                </form>
                            </td>

                            @endif
                            
                            <!-- Actions (3-dot menu) -->
                            <td class="py-4 px-6 text-right">
                                <div class="inline-block text-left" x-data="{
                                    open: false, style: '',
                                    toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => { this.place(); if (window.lucide) lucide.createIcons(); }); },
                                    place() {
                                        const r = this.$refs.btn.getBoundingClientRect(), W = 168, H = 140;
                                        let left = r.right - W; if (left < 8) left = 8;
                                        let top = (r.bottom + H > window.innerHeight) ? (r.top - H - 4) : (r.bottom + 4);
                                        this.style = `position:fixed;left:${left}px;top:${top}px;width:${W}px;`;
                                    }
                                }">
                                    <button type="button" x-ref="btn" @click.stop="toggle()" title="Actions"
                                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition dark:hover:bg-slate-700 dark:hover:text-white">
                                        <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                    </button>
                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                                             @click.outside="open = false" @keydown.escape.window="open = false"
                                             :style="style"
                                             class="z-[60] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:bg-slate-800 dark:border-slate-700">
                                            <a href="{{ route('employees.profile', $emp->user_id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                                <i data-lucide="eye" class="h-3.5 w-3.5"></i> View
                                            </a>
                                            @if($auth->canEdit($emp->user))
                                                <a href="{{ route('employees.profile.edit', $emp->user_id) }}" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700">
                                                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                                                </a>
                                            @endif
                                            @if($auth->isAdmin() && $auth->id !== $emp->user_id)
                                                <form action="{{ route('employees.deactivate', $emp->user_id) }}" method="POST"
                                                      onsubmit="return confirm('Deactivate {{ $fullName }}? They will be moved to Archived and lose access. You can restore them anytime.');">
                                                    @csrf
                                                    <button type="submit" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                                                        <i data-lucide="user-x" class="h-3.5 w-3.5"></i> Deactivate
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $auth->isAdmin() ? 6 : 4 }}" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 dark:bg-slate-750/50">
                                        <i data-lucide="users" class="h-6 w-6"></i>
                                    </div>
                                    <h3 class="mt-4 text-sm font-bold text-slate-800 dark:text-slate-250">No employees found</h3>
                                    <p class="text-xs text-slate-400 mt-0.5">There are no records corresponding to your access scope.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
            {{ $employees->links() }}
        </div>
    </div>

</div>

    <!-- Import Modal -->
    <div x-data="{ show: false }"
         x-show="show"
         @open-import-modal.window="show = true"
         class="relative z-50"
         style="display: none;"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div @click.away="show = false" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-slate-800">
                    <form action="{{ route('employees.import') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 dark:bg-slate-800">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 sm:mx-0 sm:h-10 sm:w-10 dark:bg-brand-500/20">
                                    <i data-lucide="upload-cloud" class="h-6 w-6 text-brand-600 dark:text-brand-400"></i>
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                    <h3 class="text-base font-bold leading-6 text-slate-900 dark:text-white" id="modal-title">Bulk Import Employees</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-slate-500 dark:text-slate-400">
                                            Upload a CSV file containing employee data. <br>
                                            <span class="text-xs">It supports the full profile template including contact info, salary, contract type, etc. Ensure the first row contains standard header names. Columns can be in any order.</span>
                                        </p>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">CSV File</label>
                                        <input type="file" name="import_file" accept=".csv" required class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-400 dark:file:hover:bg-brand-500/20">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 dark:bg-slate-800/50">
                            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-brand-600 px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm hover:bg-brand-700 sm:ml-3 sm:w-auto">Import</button>
                            <button type="button" @click="show = false" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto dark:bg-slate-700 dark:text-white dark:ring-slate-600 dark:hover:bg-slate-600">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
