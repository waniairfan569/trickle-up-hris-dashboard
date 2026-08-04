@php
    $routeName = request()->route()?->getName() ?? '';

    // Sidebar red badges — items needing attention on each tab.
    $nav = ['invites' => 0, 'timeoff' => 0, 'forms' => 0, 'corrections' => 0, 'sign' => 0];
    if ($navUser = auth()->user()) {
        // Documents awaiting THIS user's signature (shown on Document Library).
        $nav['sign'] = \App\Models\DocumentRequest::where('status', 'in_progress')
            ->whereHas('signers', fn ($s) => $s->where('user_id', $navUser->id)->where('status', 'pending'))
            ->with('signers')
            ->get()
            ->filter(fn ($r) => $r->isAwaiting($navUser))
            ->count();
        $navIsAdmin = $navUser->hasRole('super_admin') || $navUser->hasRole('hr_admin');
        $navReportIds = (!$navIsAdmin && $navUser->isManager() && method_exists($navUser, 'teamMemberIds'))
            ? $navUser->teamMemberIds() : collect();

        // NEW forms assigned to me since I last opened My Forms (badge clears on visit).
        $nav['forms'] = \App\Models\FormSubmission::where('user_id', $navUser->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('form', fn ($q) => $q->where('status', '!=', 'draft'))
            ->when($navUser->forms_last_seen_at, fn ($q) => $q->where('created_at', '>', $navUser->forms_last_seen_at))
            ->count();

        // Time-off awaiting a decision (approver's view; else my own pending)
        if ($navIsAdmin) {
            $nav['timeoff'] = \App\Models\TimeOffRequest::where('status', 'pending')->count();
            $nav['invites'] = \App\Models\User::where('account_status', 'invited')->count();
            $nav['corrections'] = \App\Models\AttendanceCorrection::where('status', 'pending')->count();
        } elseif ($navReportIds->isNotEmpty()) {
            $nav['timeoff'] = \App\Models\TimeOffRequest::where('status', 'pending')->whereIn('user_id', $navReportIds)->count();
            $nav['corrections'] = \App\Models\AttendanceCorrection::where('status', 'pending')->whereIn('user_id', $navReportIds)->count();
        } else {
            $nav['timeoff'] = \App\Models\TimeOffRequest::where('user_id', $navUser->id)->where('status', 'pending')->count();
        }
    }

    $navBadge = fn ($n) => $n > 0
        ? '<span class="ml-auto inline-flex items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold h-5 min-w-5 px-1.5 leading-none">' . $n . '</span>'
        : '';
@endphp

<!-- Navigation Group: Primary -->
<div class="space-y-1">
    @if(optional(auth()->user())->isOperator())
    <a href="{{ route('operator.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'operator') ? 'bg-indigo-600 text-white shadow-md' : 'text-indigo-300 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="server" class="h-4 w-4 shrink-0"></i>
        <span>Operator Console</span>
    </a>
    @endif

    <a href="{{ route('dashboard') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'dashboard') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
        <i data-lucide="layout-dashboard" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'dashboard') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Dashboard</span>
    </a>
</div>

<!-- Navigation Group: Menu (personal / team / comms) — one divider, evenly spaced groups -->
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
@php
    $myWorkspaceOpen = (Str::startsWith($routeName, 'employees.profile') || request()->is('employees/' . auth()->id() . '/profile*'))
        || Str::startsWith($routeName, 'attendance.my-history')
        || Str::startsWith($routeName, 'shifts.my-schedule')
        || (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies'))
        || Str::startsWith($routeName, 'performance')
        || Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')
        || Str::startsWith($routeName, 'company-forms.my-reviews')
        || Str::startsWith($routeName, 'my-policies') || Str::startsWith($routeName, 'policies.')
        || request()->routeIs('equipment.index')
        || (request()->routeIs('employees.index') && !(auth()->user()->isAdmin() || auth()->user()->isManager()))
        || Str::startsWith($routeName, 'document-library') || Str::startsWith($routeName, 'documents.');
@endphp
<div x-data="{ open: {{ $myWorkspaceOpen ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
       class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $myWorkspaceOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <span class="flex items-center gap-x-3"><i data-lucide="briefcase" class="h-4 w-4 shrink-0 transition {{ $myWorkspaceOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> My Workspace</span>
        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
    </button>
    <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
        <a href="{{ route('employees.profile', auth()->id()) }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'employees.profile') || request()->is('employees/' . auth()->id() . '/profile*')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="user" class="h-4 w-4 shrink-0"></i><span class="flex-1">My Profile</span>
        </a>
        {{-- Employees Directory: admins/managers get it under Team Management; show it here for everyone else. --}}
        @unless(auth()->user()->isAdmin() || auth()->user()->isManager())
        <a href="{{ route('employees.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('employees.index') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="users" class="h-4 w-4 shrink-0"></i><span class="flex-1">Employees Directory</span>
        </a>
        @endunless
        <a href="{{ route('attendance.my-history') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance.my-history') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="clock" class="h-4 w-4 shrink-0"></i><span class="flex-1">My Attendance</span>
        </a>
        <a href="{{ route('shifts.my-schedule') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'shifts.my-schedule') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="calendar-clock" class="h-4 w-4 shrink-0"></i><span class="flex-1">My Schedule</span>
        </a>
        <a href="{{ route('time-off.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="calendar" class="h-4 w-4 shrink-0"></i><span class="flex-1">Time Off Requests</span>
            {!! $navBadge($nav['timeoff']) !!}
        </a>
        <a href="{{ route('performance.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'performance') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="award" class="h-4 w-4 shrink-0"></i><span class="flex-1">Performance Reviews</span>
        </a>
        <a href="{{ route('my-forms.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0"></i><span class="flex-1">My Forms</span>
            {!! $navBadge($nav['forms']) !!}
        </a>
        @if(!auth()->user()->isAdmin() && auth()->user()->reviewableForms()->exists())
        <a href="{{ route('company-forms.my-reviews') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-forms.my-reviews') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="clipboard-check" class="h-4 w-4 shrink-0"></i><span class="flex-1">Form Reviews</span>
        </a>
        @endif
        <a href="{{ route('my-policies.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'my-policies') || Str::startsWith($routeName, 'policies.')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="book-text" class="h-4 w-4 shrink-0"></i><span class="flex-1">My Policies</span>
        </a>
        <a href="{{ route('equipment.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('equipment.index') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="package" class="h-4 w-4 shrink-0"></i><span class="flex-1">Equipment</span>
        </a>
        @php $docLibActive = Str::startsWith($routeName, 'document-library') || Str::startsWith($routeName, 'documents.'); @endphp
        <a href="{{ route('document-library.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ $docLibActive ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="library" class="h-4 w-4 shrink-0"></i><span class="flex-1">Document Library</span>
            {!! $navBadge($nav['sign'] ?? 0) !!}
        </a>
    </div>
</div>

<!-- Navigation Group: Team Management (managers) -->
@role('manager,hr_admin,super_admin')
@php
    $teamOpen = collect(['attendance.live', 'attendance.on-leave', 'attendance.team', 'attendance.corrections', 'probation'])->contains(fn ($r) => Str::startsWith($routeName, $r))
        || (Str::startsWith($routeName, 'employees') && !Str::endsWith($routeName, 'profile') && !Str::endsWith($routeName, 'pending-invitations') && !request()->is('employees/' . auth()->id() . '/profile*'));
@endphp
<div x-data="{ open: {{ $teamOpen ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
       class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $teamOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <span class="flex items-center gap-x-3"><i data-lucide="users-round" class="h-4 w-4 shrink-0 transition {{ $teamOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Team Management</span>
        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
    </button>
    <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
        <a href="{{ route('attendance.live') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance.live') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="activity" class="h-4 w-4 shrink-0"></i><span class="flex-1">Live Board</span>
        </a>
        <a href="{{ route('employees.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'employees') && !Str::endsWith($routeName, 'profile') && !Str::endsWith($routeName, 'pending-invitations') && !request()->is('employees/' . auth()->id() . '/profile*')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="users" class="h-4 w-4 shrink-0"></i><span class="flex-1">Employees Directory</span>
        </a>
        <a href="{{ route('attendance.on-leave') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance.on-leave') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="palmtree" class="h-4 w-4 shrink-0"></i><span class="flex-1">On Leave</span>
        </a>
        <a href="{{ route('attendance.team') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance.team') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0"></i><span class="flex-1">Team Attendance</span>
        </a>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('probation.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'probation') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="clipboard-check" class="h-4 w-4 shrink-0"></i><span class="flex-1">Probation</span>
        </a>
        @endif
        <a href="{{ route('attendance.corrections') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance.corrections') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="file-check-2" class="h-4 w-4 shrink-0"></i><span class="flex-1">Pending Corrections</span>
            {!! $navBadge($nav['corrections']) !!}
        </a>
    </div>
</div>
@endrole

<!-- Navigation Group: Communication -->
@role('super_admin,hr_admin')
@php $commOpen = Str::startsWith($routeName, 'announcements'); @endphp
<div x-data="{ open: {{ $commOpen ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
       class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $commOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <span class="flex items-center gap-x-3"><i data-lucide="megaphone" class="h-4 w-4 shrink-0 transition {{ $commOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Communication</span>
        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
    </button>
    <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
        <a href="{{ route('announcements.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'announcements') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="megaphone" class="h-4 w-4 shrink-0"></i><span class="flex-1">Announcements</span>
        </a>
    </div>
</div>
@endrole

<!-- Navigation Group: Calendar & Events -->
@role('super_admin,hr_admin')
@php $calOpen = Str::startsWith($routeName, 'events'); @endphp
<div x-data="{ open: {{ $calOpen ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open"
       class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $calOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <span class="flex items-center gap-x-3"><i data-lucide="calendar-days" class="h-4 w-4 shrink-0 transition {{ $calOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Calendar &amp; Events</span>
        <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
    </button>
    <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
        <a href="{{ route('events.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'events') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
            <i data-lucide="calendar-heart" class="h-4 w-4 shrink-0"></i><span class="flex-1">Events</span>
        </a>
    </div>
</div>
@endrole
</div>
{{-- end Menu section --}}

<!-- Navigation Group: Administration -->
@role('super_admin,hr_admin')
@php
    $companySettingsRoutes = ['company-entities', 'company-forms', 'company-policies', 'company-documents', 'document-categories', 'workspace.branding', 'billing'];
    $companySettingsOpen = collect($companySettingsRoutes)->contains(fn ($r) => Str::startsWith($routeName, $r));
    $companyGroupOpen = $companySettingsOpen || Str::startsWith($routeName, 'departments') || Str::startsWith($routeName, 'office-locations');
    $templatesOpen = Str::startsWith($routeName, 'profile-templates') || Str::startsWith($routeName, 'profile-sections') || Str::startsWith($routeName, 'profile-fields') || Str::startsWith($routeName, 'signature-templates');
    $timeSettingsOpen = collect(['time-off-policies', 'leave-year-settings', 'leave-encashments', 'time-tracking-policies', 'shifts'])->contains(fn ($r) => Str::startsWith($routeName, $r));
    $attnOpen = collect(['attendance-reports', 'employees.attendance-mode'])->contains(fn ($r) => Str::startsWith($routeName, $r));
    $timeAttGroupOpen = $timeSettingsOpen || $attnOpen;
    $securityGroupOpen = Str::startsWith($routeName, 'account.security') || request()->routeIs('roles.*') || Str::startsWith($routeName, 'admin.sessions') || Str::startsWith($routeName, 'admin.audit-logs');
    $devicesOpen = Str::startsWith($routeName, 'zkteco');
@endphp
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Administration</div>

    {{-- Company --}}
    <div x-data="{ open: {{ $companyGroupOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $companyGroupOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="building" class="h-4 w-4 shrink-0 transition {{ $companyGroupOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Company</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <div x-data="{ open: {{ $companySettingsOpen ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition {{ $companySettingsOpen ? 'text-white' : 'text-slate-400 hover:text-white' }}">
                    <span class="flex items-center gap-x-3"><i data-lucide="settings" class="h-4 w-4 shrink-0"></i> Company Settings</span>
                    <i data-lucide="chevron-down" class="h-3.5 w-3.5 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak class="mt-1 ml-3 pl-3 border-l border-slate-800/60 space-y-1">
                    <a href="{{ route('company-entities.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-entities') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="building-2" class="h-4 w-4 shrink-0"></i><span class="flex-1">General</span></a>
                    <a href="{{ route('company-forms.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-forms') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="clipboard-list" class="h-4 w-4 shrink-0"></i><span class="flex-1">Company Forms</span></a>
                    <a href="{{ route('company-policies.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-policies') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="book-text" class="h-4 w-4 shrink-0"></i><span class="flex-1">Company Policies</span></a>
                    <a href="{{ route('company-documents.admin') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'company-documents') || Str::startsWith($routeName, 'document-categories')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="file-text" class="h-4 w-4 shrink-0"></i><span class="flex-1">Company Documents</span></a>
                    <a href="{{ route('workspace.branding') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'workspace.branding') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="palette" class="h-4 w-4 shrink-0"></i><span class="flex-1">Workspace Branding</span></a>
                    <a href="{{ route('billing.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'billing') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="credit-card" class="h-4 w-4 shrink-0"></i><span class="flex-1">Billing &amp; Plans</span></a>
                </div>
            </div>
            <a href="{{ route('departments.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'departments') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="network" class="h-4 w-4 shrink-0"></i><span class="flex-1">Departments</span></a>
            <a href="{{ route('office-locations.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'office-locations') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="map-pin" class="h-4 w-4 shrink-0"></i><span class="flex-1">Office Locations</span></a>
        </div>
    </div>

    {{-- Templates --}}
    <div x-data="{ open: {{ $templatesOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $templatesOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="layout-template" class="h-4 w-4 shrink-0 transition {{ $templatesOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Templates</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <a href="{{ route('profile-templates.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'profile-templates') || Str::startsWith($routeName, 'profile-sections') || Str::startsWith($routeName, 'profile-fields')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="user-cog" class="h-4 w-4 shrink-0"></i><span class="flex-1">Profile Templates</span></a>
            <a href="{{ route('signature-templates.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'signature-templates') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="signature" class="h-4 w-4 shrink-0"></i><span class="flex-1">Signature Templates</span></a>
        </div>
    </div>

    {{-- Time & Attendance --}}
    <div x-data="{ open: {{ $timeAttGroupOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $timeAttGroupOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="calendar-clock" class="h-4 w-4 shrink-0 transition {{ $timeAttGroupOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Time &amp; Attendance</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <div x-data="{ open: {{ $timeSettingsOpen ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition {{ $timeSettingsOpen ? 'text-white' : 'text-slate-400 hover:text-white' }}">
                    <span class="flex items-center gap-x-3"><i data-lucide="timer" class="h-4 w-4 shrink-0"></i> Time Settings</span>
                    <i data-lucide="chevron-down" class="h-3.5 w-3.5 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak class="mt-1 ml-3 pl-3 border-l border-slate-800/60 space-y-1">
                    <a href="{{ route('time-off-policies.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'time-off-policies') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="calendar" class="h-4 w-4 shrink-0"></i><span class="flex-1">Time Off Policies</span></a>
                    <a href="{{ route('leave-year-settings.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'leave-year-settings') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="calendar-days" class="h-4 w-4 shrink-0"></i><span class="flex-1">Leave Year &amp; Encashment</span></a>
                    <a href="{{ route('leave-encashments.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'leave-encashments.index') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="wallet" class="h-4 w-4 shrink-0"></i><span class="flex-1">Encashment Records</span></a>
                    <a href="{{ route('time-tracking-policies.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'time-tracking-policies') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="clock" class="h-4 w-4 shrink-0"></i><span class="flex-1">Time Tracking</span></a>
                    <a href="{{ route('shifts.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'shifts.index') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="calendar-clock" class="h-4 w-4 shrink-0"></i><span class="flex-1">Shift Management</span></a>
                </div>
            </div>
            <div x-data="{ open: {{ $attnOpen ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold transition {{ $attnOpen ? 'text-white' : 'text-slate-400 hover:text-white' }}">
                    <span class="flex items-center gap-x-3"><i data-lucide="calendar-check" class="h-4 w-4 shrink-0"></i> Attendance</span>
                    <i data-lucide="chevron-down" class="h-3.5 w-3.5 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="open" x-cloak class="mt-1 ml-3 pl-3 border-l border-slate-800/60 space-y-1">
                    <a href="{{ route('attendance-reports.settings') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance-reports') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="bar-chart-3" class="h-4 w-4 shrink-0"></i><span class="flex-1">Attendance Reports</span></a>
                    <a href="{{ route('employees.attendance-mode') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'employees.attendance-mode') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="sliders-horizontal" class="h-4 w-4 shrink-0"></i><span class="flex-1">Attendance Mode</span></a>
                </div>
            </div>
        </div>
    </div>

    {{-- Security --}}
    <div x-data="{ open: {{ $securityGroupOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $securityGroupOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="shield-check" class="h-4 w-4 shrink-0 transition {{ $securityGroupOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Security</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <a href="{{ route('account.security') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'account.security') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="lock" class="h-4 w-4 shrink-0"></i><span class="flex-1">Security</span></a>
            <a href="{{ route('roles.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('roles.*') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="shield" class="h-4 w-4 shrink-0"></i><span class="flex-1">Roles &amp; Permissions</span></a>
            <a href="{{ route('admin.sessions.index') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'admin.sessions') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="monitor-smartphone" class="h-4 w-4 shrink-0"></i><span class="flex-1">Active Sessions</span></a>
            <a href="{{ route('admin.audit-logs') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'admin.audit-logs') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="history" class="h-4 w-4 shrink-0"></i><span class="flex-1">System Audit Logs</span></a>
        </div>
    </div>

    {{-- Devices --}}
    <div x-data="{ open: {{ $devicesOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $devicesOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="hard-drive" class="h-4 w-4 shrink-0 transition {{ $devicesOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Devices</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <a href="{{ route('zkteco.dashboard') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'zkteco') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}"><i data-lucide="fingerprint" class="h-4 w-4 shrink-0"></i><span class="flex-1">ZKTeco Devices</span></a>
        </div>
    </div>
</div>

<!-- Navigation Group: Requests -->
@php
    $pendingCodes = \App\Models\CodeRequest::where('status', 'pending')->count();
    $pendingEquipment = \App\Models\EquipmentRequest::where('status', 'pending')->count();
@endphp
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Requests</div>
    <a href="{{ route('equipment.admin') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ request()->routeIs('equipment.admin') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
        <i data-lucide="package" class="h-4 w-4 shrink-0"></i>
        <span class="flex-1">Equipment Requests</span>
        @if($pendingEquipment > 0)<span class="inline-flex items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold h-5 min-w-5 px-1">{{ $pendingEquipment }}</span>@endif
    </a>
    <a href="{{ route('code-requests.pending') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'code-requests.pending') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
        <i data-lucide="key-round" class="h-4 w-4 shrink-0"></i>
        <span class="flex-1">Code Requests</span>
        @if($pendingCodes > 0)<span class="inline-flex items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold h-5 min-w-5 px-1">{{ $pendingCodes }}</span>@endif
    </a>
</div>

<!-- Navigation Group: Invitations -->
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Invitations</div>
    <a href="{{ route('employees.pending-invitations') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'employees.pending-invitations') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">
        <i data-lucide="mail-warning" class="h-4 w-4 shrink-0"></i>
        <span class="flex-1">Pending Invitations</span>
        {!! $navBadge($nav['invites']) !!}
    </a>
</div>
@endrole
