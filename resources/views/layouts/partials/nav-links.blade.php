@php
    $routeName = request()->route()?->getName() ?? '';

    // Sidebar red badges — items needing attention on each tab.
    $nav = ['invites' => 0, 'timeoff' => 0, 'forms' => 0, 'corrections' => 0, 'sign' => 0, 'hr_to_sign' => 0];
    if ($navUser = auth()->user()) {
        // Documents awaiting THIS user's signature (shown on Document Library).
        $nav['sign'] = \App\Models\DocumentRequest::where('status', 'in_progress')
            ->whereHas('signers', fn ($s) => $s->where('user_id', $navUser->id)->where('status', 'pending'))
            ->with('signers')
            ->get()
            ->filter(fn ($r) => $r->isAwaiting($navUser))
            ->count();

        // HR documents (lateness review, return to work, …) sent to me to sign.
        $nav['hr_to_sign'] = \App\Models\HrDocumentSigner::where('user_id', $navUser->id)->whereNull('signed_at')->count();
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

<!-- Navigation Group: Menu (personal / team / comms) — one divider, evenly spaced groups -->
<div class="space-y-1">
    {{-- Personal menu — top-level items (no "My Workspace" dropdown) --}}
        <a href="{{ route('employees.profile', auth()->id()) }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'employees.profile') || request()->is('employees/' . auth()->id() . '/profile*')) ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="user" class="h-5 w-5 shrink-0"></i><span class="flex-1">My Profile</span>
        </a>
        {{-- Employees get the Org Chart here; admins/managers get the full Employees Directory under Team Management. --}}
        @unless(auth()->user()->isAdmin() || auth()->user()->isManager())
        <a href="{{ route('org-chart') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('org-chart') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="network" class="h-5 w-5 shrink-0"></i><span class="flex-1">Org Chart</span>
        </a>
        @endunless
        <a href="{{ route('attendance.my-history') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'attendance.my-history') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="clock" class="h-5 w-5 shrink-0"></i><span class="flex-1">My Attendance</span>
        </a>
        <a href="{{ route('time-off.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'time-off') && !Str::contains($routeName, 'policies')) ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="calendar" class="h-5 w-5 shrink-0"></i><span class="flex-1">Time Off Requests</span>
            {!! $navBadge($nav['timeoff']) !!}
        </a>
        <a href="{{ route('events.employee-calendar') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('events.employee-calendar') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="calendar-days" class="h-5 w-5 shrink-0"></i><span class="flex-1">Calendar</span>
        </a>
        @unless(auth()->user()->isAdmin())
        <a href="{{ route('announcements.all') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('announcements.all') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="megaphone" class="h-5 w-5 shrink-0"></i><span class="flex-1">Announcements</span>
        </a>
        @if(plan_allows('feedback'))
        <a href="{{ route('feedback.mine') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('feedback.mine') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="message-square-heart" class="h-5 w-5 shrink-0"></i><span class="flex-1">Feedback &amp; Suggestions</span>
        </a>
        @endif
        @endunless
        @if(plan_allows('forms'))
        <a href="{{ route('my-forms.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'my-forms') || Str::startsWith($routeName, 'forms.')) ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="clipboard-list" class="h-5 w-5 shrink-0"></i><span class="flex-1">My Forms</span>
            {!! $navBadge($nav['forms']) !!}
        </a>
        @endif
        @if(!auth()->user()->isAdmin() && plan_allows('forms') && auth()->user()->reviewableForms()->exists())
        <a href="{{ route('company-forms.my-reviews') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'company-forms.my-reviews') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="clipboard-check" class="h-5 w-5 shrink-0"></i><span class="flex-1">Form Reviews</span>
        </a>
        @endif
        <a href="{{ route('my-policies.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'my-policies') || Str::startsWith($routeName, 'policies.')) ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="book-text" class="h-5 w-5 shrink-0"></i><span class="flex-1">My Policies</span>
        </a>
        @if(plan_allows('hr_documents'))
        <a href="{{ route('hr-documents.to-sign') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ (Str::startsWith($routeName, 'hr-documents.to-sign') || Str::startsWith($routeName, 'hr-documents.sign')) ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="file-signature" class="h-5 w-5 shrink-0"></i><span class="flex-1">To Sign</span>
            {!! $navBadge($nav['hr_to_sign'] ?? 0) !!}
        </a>
        @endif
        @if(plan_allows('equipment'))
        <a href="{{ route('equipment.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs('equipment.index') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="package" class="h-5 w-5 shrink-0"></i><span class="flex-1">Equipment</span>
        </a>
        @endif
        @php $docLibActive = Str::startsWith($routeName, 'document-library') || Str::startsWith($routeName, 'documents.'); @endphp
        <a href="{{ route('document-library.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ $docLibActive ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="library" class="h-5 w-5 shrink-0"></i><span class="flex-1">Document Library</span>
            {!! $navBadge($nav['sign'] ?? 0) !!}
        </a>
        <a href="{{ route('settings.index') }}"
           class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'settings') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
            <i data-lucide="settings" class="h-5 w-5 shrink-0"></i><span class="flex-1">Settings</span>
        </a>

        {{-- Granted access: features a super admin has granted this employee (non-admins only) --}}
        @php
            $__grantLinks = [];
            if (auth()->check() && !auth()->user()->isAdmin()) {
                foreach (auth()->user()->grantedFeatureKeys() as $__fk) {
                    $__home = \App\Support\FeatureCatalog::homeRoute($__fk);
                    $__plan = \App\Support\FeatureCatalog::planFeature($__fk);
                    if ($__home && \Illuminate\Support\Facades\Route::has($__home) && (!$__plan || plan_allows($__plan))) {
                        $__grantLinks[$__home] = \App\Support\FeatureCatalog::navLabel($__fk);
                    }
                }
            }
            // Code-send delegation lives on users.can_send_codes (not the feature catalog) — surface it here too.
            $__canSendCodes = auth()->check() && !auth()->user()->isAdmin() && auth()->user()->can_send_codes && plan_allows('code_requests');
            $__pendingCodesGranted = $__canSendCodes ? \App\Models\CodeRequest::where('status', 'pending')->count() : 0;
        @endphp
        @if(!empty($__grantLinks) || $__canSendCodes)
            <div class="mt-4 pt-3 border-t border-slate-800">
                <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">Granted access</p>
                @foreach($__grantLinks as $__route => $__label)
                    <a href="{{ route($__route) }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ request()->routeIs($__route) ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                        <i data-lucide="key-round" class="h-5 w-5 shrink-0"></i><span class="flex-1">{{ $__label }}</span>
                    </a>
                @endforeach
                @if($__canSendCodes)
                    <a href="{{ route('code-requests.pending') }}" class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-semibold transition {{ Str::startsWith($routeName, 'code-requests.pending') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                        <i data-lucide="key-round" class="h-5 w-5 shrink-0"></i><span class="flex-1">Code Requests</span>
                        @if($__pendingCodesGranted > 0)<span class="inline-flex items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold h-5 min-w-5 px-1">{{ $__pendingCodesGranted }}</span>@endif
                    </a>
                @endif
            </div>
        @endif

@php
    // Team Management hub + every page it links to (shared by the manager-only link below and the Administration block).
    $teamActive = collect(['team.index', 'attendance.live', 'attendance.on-leave', 'attendance.team', 'attendance.corrections', 'probation', 'admin.reminders'])->contains(fn ($r) => Str::startsWith($routeName, $r))
        || (Str::startsWith($routeName, 'employees') && !Str::endsWith($routeName, 'profile') && !request()->is('employees/' . auth()->id() . '/profile*'));
    $commActive = Str::startsWith($routeName, 'communication') || Str::startsWith($routeName, 'announcements') || (Str::startsWith($routeName, 'events') && !request()->routeIs('events.employee-calendar'));
@endphp

{{-- Team Management for managers who are not admins — admins get it under Administration instead --}}
@if(auth()->user()->isManager() && !auth()->user()->isAdmin())
<a href="{{ route('team.index') }}"
   class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $teamActive ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
    <i data-lucide="users-round" class="h-5 w-5 shrink-0 transition {{ $teamActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
    <span class="flex-1">Team Management</span>
    {!! $navBadge($nav['corrections']) !!}
</a>
@endif
</div>
{{-- end Menu section --}}

<!-- Navigation Group: Administration -->
@role('super_admin,hr_admin')
@php
    $companySettingsRoutes = ['company.index', 'company-entities', 'company-forms', 'company-policies', 'company-documents', 'document-categories', 'workspace.branding', 'billing', 'developer'];
    $companySettingsOpen = collect($companySettingsRoutes)->contains(fn ($r) => Str::startsWith($routeName, $r));
    $templatesOpen = Str::startsWith($routeName, 'templates.index') || Str::startsWith($routeName, 'profile-templates') || Str::startsWith($routeName, 'profile-sections') || Str::startsWith($routeName, 'profile-fields') || Str::startsWith($routeName, 'signature-templates');
    $timeSettingsOpen = collect(['time-off-policies', 'leave-year-settings', 'leave-encashments', 'time-tracking-policies', 'shifts'])->contains(fn ($r) => Str::startsWith($routeName, $r));
    $attnOpen = collect(['attendance-reports', 'employees.attendance-mode', 'company-wfh-days', 'reports.'])->contains(fn ($r) => Str::startsWith($routeName, $r));
    // Admin HR-documents pages only — the personal To Sign / sign / my-pdf routes share the prefix but belong to the personal menu.
    $hrDocsOpen = Str::startsWith($routeName, 'hr-documents') && !in_array($routeName, ['hr-documents.to-sign', 'hr-documents.sign', 'hr-documents.sign.store', 'hr-documents.my-pdf'], true);
    $timeAttGroupOpen = Str::startsWith($routeName, 'time-attendance.index') || $timeSettingsOpen || $attnOpen || $hrDocsOpen;
    $securityGroupOpen = Str::startsWith($routeName, 'security.index') || Str::startsWith($routeName, 'account.security') || request()->routeIs('roles.*') || Str::startsWith($routeName, 'admin.sessions') || Str::startsWith($routeName, 'admin.audit-logs');
    $devicesOpen = Str::startsWith($routeName, 'devices.index') || Str::startsWith($routeName, 'zkteco');
    // Company covers its own settings pages plus the Security and Devices sub-hubs (must come after those two flags).
    $companyGroupOpen = $companySettingsOpen || Str::startsWith($routeName, 'departments') || Str::startsWith($routeName, 'office-locations') || $securityGroupOpen || $devicesOpen;
@endphp
<div class="mt-6 pt-6 border-t border-slate-850 space-y-1">
    <div class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">Administration</div>

    {{-- Team Management — single link to the hub page; the team tools live there as tiles --}}
    <a href="{{ route('team.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $teamActive ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="users-round" class="h-5 w-5 shrink-0 transition {{ $teamActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Team Management</span>
        {!! $navBadge($nav['corrections'] + $nav['invites']) !!}
    </a>

    {{-- Communication — single link to the hub page; Announcements + Events live there as tiles --}}
    <a href="{{ route('communication.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $commActive ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="megaphone" class="h-5 w-5 shrink-0 transition {{ $commActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Communication</span>
    </a>

    {{-- Company — single link to the hub page; all company settings live there as tiles --}}
    <a href="{{ route('company.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $companyGroupOpen ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="building" class="h-5 w-5 shrink-0 transition {{ $companyGroupOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Company</span>
    </a>

    {{-- Templates — single link to the hub page; Profile + Signature templates live there as tiles --}}
    <a href="{{ route('templates.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $templatesOpen ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="layout-template" class="h-5 w-5 shrink-0 transition {{ $templatesOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Templates</span>
    </a>

    {{-- Time & Attendance — single link to the hub page; time settings, attendance and documents live there as tiles --}}
    <a href="{{ route('time-attendance.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $timeAttGroupOpen ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="calendar-clock" class="h-5 w-5 shrink-0 transition {{ $timeAttGroupOpen ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Time &amp; Attendance</span>
    </a>

    {{-- Requests — single link to the hub page; Equipment / Code / Feedback queues live there as tiles --}}
    @if(plan_allows('equipment') || plan_allows('code_requests') || plan_allows('feedback'))
    @php
        $requestsActive = Str::startsWith($routeName, 'requests.index') || request()->routeIs('equipment.admin', 'equipment.export', 'feedback.admin') || Str::startsWith($routeName, 'code-requests.pending');
        $requestsBadge = (plan_allows('equipment') ? \App\Models\EquipmentRequest::where('status', 'pending')->count() : 0)
            + (plan_allows('code_requests') ? \App\Models\CodeRequest::where('status', 'pending')->count() : 0)
            + (plan_allows('feedback') ? \App\Models\Feedback::where('status', 'open')->count() : 0);
    @endphp
    <a href="{{ route('requests.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ $requestsActive ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="inbox" class="h-5 w-5 shrink-0 transition {{ $requestsActive ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Requests</span>
        {!! $navBadge($requestsBadge) !!}
    </a>
    @endif

    {{-- Linked Sheets — bookmarks to external Google Sheets / spreadsheets (a library of links, not reports) --}}
    @if(plan_allows('sheets'))
    <a href="{{ route('sheets.index') }}"
       class="flex items-center gap-x-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition duration-150 group {{ Str::startsWith($routeName, 'sheets') ? 'text-brand-400 bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
        <i data-lucide="sheet" class="h-5 w-5 shrink-0 transition {{ Str::startsWith($routeName, 'sheets') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"></i>
        <span class="flex-1">Linked Sheets</span>
    </a>
    @endif
</div>
@endrole
