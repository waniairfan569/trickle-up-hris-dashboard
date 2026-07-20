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
        $navReportIds = (!$navIsAdmin && method_exists($navUser, 'directReports'))
            ? $navUser->directReports()->pluck('id') : collect();

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

    @role('super_admin,hr_admin')
    <a href="{{ route('account.security') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'account.security') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="shield-check" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'account.security') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Security</span>
    </a>
    @endrole

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
        {!! $navBadge($nav['invites']) !!}
    </a>
    @endif

    <a href="{{ route('time-off.index') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="calendar" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Time Off Requests</span>
        {!! $navBadge($nav['timeoff']) !!}
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

    {{-- "Documents" (sign inbox) merged into Document Library below. --}}

    <a href="{{ route('my-forms.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>My Forms</span>
        {!! $navBadge($nav['forms']) !!}
    </a>

    @if(!auth()->user()->isAdmin() && auth()->user()->reviewableForms()->exists())
    <a href="{{ route('company-forms.my-reviews') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'company-forms.my-reviews') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-check" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'company-forms.my-reviews') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Form Reviews</span>
    </a>
    @endif

    <a href="{{ route('my-policies.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ (Str::startsWith($routeName, 'my-policies') || Str::startsWith($routeName, 'policies.')) ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="book-text" class="h-4 w-4 shrink-0 transition {{ (Str::startsWith($routeName, 'my-policies') || Str::startsWith($routeName, 'policies.')) ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>My Policies</span>
    </a>

    @php $docLibActive = Str::startsWith($routeName, 'document-library') || Str::startsWith($routeName, 'documents.'); @endphp
    <a href="{{ route('document-library.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $docLibActive ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="library" class="h-4 w-4 shrink-0 transition {{ $docLibActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Document Library</span>
        {!! $navBadge($nav['sign'] ?? 0) !!}
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

    <a href="{{ route('attendance.on-leave') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.on-leave') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="palmtree" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.on-leave') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>On Leave</span>
    </a>

    @if(auth()->user()->isAdmin())
    <a href="{{ route('probation.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'probation') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-check" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'probation') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Probation</span>
    </a>
    @endif

    <a href="{{ route('attendance.team') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.team') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="clipboard-list" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.team') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Team Attendance</span>
    </a>

    <a href="{{ route('attendance.corrections') }}" 
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'attendance.corrections') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="file-check-2" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'attendance.corrections') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Pending Corrections</span>
        {!! $navBadge($nav['corrections']) !!}
    </a>
</div>
@endrole

<!-- Navigation Group: Administrative (Conditional) -->
@role('super_admin,hr_admin')
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Administration</div>
    
    @php
        $companyChildRoutes = ['company-entities', 'company-forms', 'company-policies', 'company-documents', 'document-categories', 'workspace.branding'];
        $companyOpen = collect($companyChildRoutes)->contains(fn ($r) => Str::startsWith($routeName, $r));
    @endphp
    <div x-data="{ open: {{ $companyOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $companyOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="building" class="h-4 w-4 shrink-0 transition {{ $companyOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Company Settings</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <a href="{{ route('company-entities.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-entities') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">General</a>
            <a href="{{ route('company-forms.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-forms') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Company Forms</a>
            <a href="{{ route('company-policies.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-policies') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Company Policies</a>
            <a href="{{ route('company-documents.admin') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'company-documents') || Str::startsWith($routeName, 'document-categories')) ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Company Documents</a>
            <a href="{{ route('workspace.branding') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'workspace.branding') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Workspace Branding</a>
        </div>
    </div>

    <a href="{{ route('announcements.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'announcements') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="megaphone" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'announcements') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Announcements</span>
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

    {{-- Document Templates merged into Company Documents (toggle "Requires signature" on a company document). --}}

    @php
        $timeOpen = collect(['time-off-policies', 'time-tracking-policies', 'shifts'])->contains(fn ($r) => Str::startsWith($routeName, $r));
    @endphp
    <div x-data="{ open: {{ $timeOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $timeOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="timer" class="h-4 w-4 shrink-0 transition {{ $timeOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Time Settings</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <a href="{{ route('time-off-policies.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'time-off-policies') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Time Off Policies</a>
            <a href="{{ route('time-tracking-policies.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'time-tracking-policies') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Time Tracking</a>
            <a href="{{ route('shifts.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'shifts.index') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Shift Management</a>
        </div>
    </div>

    <a href="{{ route('events.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'events') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="calendar-heart" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'events') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Events</span>
    </a>

    @php
        $attnOpen = collect(['attendance-reports', 'employees.attendance-mode'])->contains(fn ($r) => Str::startsWith($routeName, $r));
    @endphp
    <div x-data="{ open: {{ $attnOpen ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open"
           class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $attnOpen ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <span class="flex items-center gap-x-3"><i data-lucide="calendar-clock" class="h-4 w-4 shrink-0 transition {{ $attnOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i> Attendance</span>
            <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''"></i>
        </button>
        <div x-show="open" x-cloak class="mt-1 ml-4 pl-3 border-l border-slate-800 space-y-1">
            <a href="{{ route('attendance-reports.settings') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance-reports') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Attendance Reports</a>
            <a href="{{ route('employees.attendance-mode') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'employees.attendance-mode') ? 'text-brand-400' : 'text-slate-400 hover:text-white' }}">Attendance Mode</a>
        </div>
    </div>

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

    <a href="{{ route('admin.sessions.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'admin.sessions') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="monitor-smartphone" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'admin.sessions') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span>Active Sessions</span>
    </a>

    @php $pendingCodes = \App\Models\CodeRequest::where('status', 'pending')->count(); @endphp
    <a href="{{ route('code-requests.pending') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'code-requests.pending') ? 'bg-brand-600 text-slate-900 shadow-md shadow-brand-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="key-round" class="h-4 w-4 shrink-0 transition {{ Str::startsWith($routeName, 'code-requests.pending') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Code Requests</span>
        @if($pendingCodes > 0)<span class="inline-flex items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold h-5 min-w-5 px-1">{{ $pendingCodes }}</span>@endif
    </a>
</div>
@endrole
