@php
    $routeName = request()->route()?->getName() ?? '';
@endphp

<!-- Navigation Group: Core -->
<div class="space-y-1">
    <a href="{{ route('dashboard') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'dashboard') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="layout-dashboard" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'dashboard') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Dashboard</span>
    </a>

    <a href="{{ route('employees.profile', auth()->id()) }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'employees.profile') || request()->is('employees/' . auth()->id() . '/profile*')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="user" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'employees.profile') || request()->is('employees/' . auth()->id() . '/profile*')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>My Profile</span>
    </a>

    <a href="{{ route('employees.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'employees') && !Str::endsWith($routeName, 'profile') && !Str::endsWith($routeName, 'pending-invitations') && !request()->is('employees/' . auth()->id() . '/profile*')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="users" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'employees') && !Str::endsWith($routeName, 'profile') && !Str::endsWith($routeName, 'pending-invitations') && !request()->is('employees/' . auth()->id() . '/profile*')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Employees Directory</span>
    </a>

    @if(auth()->user()->hasRole('super_admin') || auth()->user()->hasRole('hr_admin'))
    <a href="{{ route('employees.pending-invitations') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'employees.pending-invitations') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="mail-warning" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'employees.pending-invitations') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Pending Invitations</span>
    </a>
    @endif

    <a href="{{ route('time-off.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="calendar" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Time Off Requests</span>
    </a>

    <a href="{{ route('attendance.my-history') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.my-history') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clock" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.my-history') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>My Attendance</span>
    </a>

    <a href="{{ route('shifts.my-schedule') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'shifts.my-schedule') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="calendar-clock" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'shifts.my-schedule') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>My Schedule</span>
    </a>

    <a href="{{ route('performance.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'performance') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="award" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'performance') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Performance Reviews</span>
    </a>

    <a href="{{ route('documents.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'documents') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="file-signature" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'documents') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Documents</span>
    </a>

    <a href="{{ route('my-forms.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>My Forms</span>
    </a>
</div>

@role('manager,hr_admin,super_admin')
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Team Management</div>
    
    <a href="{{ route('attendance.live') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.live') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="activity" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.live') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Live Board</span>
    </a>
    
    <a href="{{ route('attendance.team') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.team') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.team') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Team Attendance</span>
    </a>

    <a href="{{ route('attendance.corrections') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.corrections') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="file-check-2" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.corrections') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Pending Corrections</span>
    </a>
</div>
@endrole

<!-- Navigation Group: Administrative (Conditional) -->
@role('super_admin,hr_admin')
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Administration</div>
    
    <a href="{{ route('company-entities.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'company-entities') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="building" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'company-entities') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Company Settings</span>
    </a>
    
    <a href="{{ route('departments.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'departments') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="building-2" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'departments') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Departments</span>
    </a>

    <a href="{{ route('profile-templates.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'profile-templates') || Str::startsWith($routeName, 'profile-sections') || Str::startsWith($routeName, 'profile-fields')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="layout-template" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'profile-templates') || Str::startsWith($routeName, 'profile-sections') || Str::startsWith($routeName, 'profile-fields')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Profile Templates</span>
    </a>

    <a href="{{ route('document-templates.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'document-templates') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="file-signature" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'document-templates') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Document Templates</span>
    </a>

    <a href="{{ route('time-off-policies.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'time-off-policies') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="settings-2" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'time-off-policies') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Time Off Policies</span>
    </a>

    <a href="{{ route('time-tracking-policies.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'time-tracking-policies') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="timer" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'time-tracking-policies') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Time Tracking</span>
    </a>

    <a href="{{ route('events.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'events') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="calendar-heart" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'events') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Events</span>
    </a>

    <a href="{{ route('attendance-reports.settings') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance-reports') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="mail-check" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance-reports') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Attendance Reports</span>
    </a>

    <a href="{{ route('company-forms.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'company-forms') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-pen" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'company-forms') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Company Forms</span>
    </a>

    <a href="{{ route('shifts.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'shifts.index') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clock-4" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'shifts.index') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Shift Management</span>
    </a>

    <a href="{{ route('office-locations.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'office-locations') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="map-pin" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'office-locations') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Office Locations</span>
    </a>

    <a href="{{ route('zkteco.dashboard') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'zkteco') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="fingerprint" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'zkteco') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>ZKTeco Devices</span>
    </a>

    <a href="{{ route('roles.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ request()->routeIs('roles.*') ? 'bg-brand-500/10 text-brand-400' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="shield-check" class="h-4 w-4 shrink-0 transition {{ request()->routeIs('roles.*') ? 'text-brand-400' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Roles & Permissions</span>
    </a>

    <a href="{{ route('admin.audit-logs') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'admin.audit-logs') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="activity" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'admin.audit-logs') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>System Audit Logs</span>
    </a>
</div>
@endrole
