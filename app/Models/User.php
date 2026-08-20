<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;

use App\Traits\RoleChecker;
use App\Traits\HasHRAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens, HasFactory, Notifiable, RoleChecker, HasHRAccess;

    protected static function booted()
    {
        static::created(function ($user) {
            $defaultShift = \App\Models\Shift::getDefault();
            if ($defaultShift && $defaultShift->auto_assign_to_new_employees) {
                \App\Models\ShiftAssignment::create([
                    'user_id' => $user->id,
                    'shift_id' => $defaultShift->id,
                    'assigned_by' => auth()->id() ?? null,
                    'assignment_type' => 'recurring',
                    'recurring_start_date' => $user->joined_at ?? now()->toDateString(),
                    'recurring_end_date' => null,
                    'recurring_days' => ["Mon","Tue","Wed","Thu","Fri"],
                    'notes' => 'Auto-assigned default shift on employee creation'
                ]);
            }

            // Auto-assign the default time-off policies (leaves) + seed balances
            // so a new employee has their leave allowances from day one.
            try {
                app(\App\Services\TimeOffBalanceService::class)
                    ->assignDefaultPolicies($user, auth()->id());
            } catch (\Throwable $e) {
                report($e); // never let policy assignment block user creation
            }
        });
    }

    protected $fillable = [
        'company_id', 'first_name', 'last_name', 'email', 'password',
        'status', 'avatar_url',
        // Profile fields:
        'phone', 'address', 'city', 'country', 'timezone', 'use_custom_timezone',
        'date_of_birth', 'nationality', 'gender', 'languages',
        'linkedin_url', 'github_url', 'portfolio_url',
        'job_title', 'employee_id', 'manager_id', 'hire_date',
        'contract_type', 'salary', 'salary_currency',
        'notice_period_days', 'years_of_experience',
        'education', 'specialization', 'skills',
        'two_factor_enabled', 'sso_provider', 'admin_notes',
        'last_login_at',
        // HRIS access system fields:
        'company_entity_id', 'job_location_id', 'department_id', 'employee_status', 'joined_at',
        // Invitation system fields:
        'account_status', 'invitation_token', 'invitation_token_hash',
        'invitation_sent_at', 'invitation_expires_at', 'invitation_accepted_at',
        'must_change_password', 'invited_by', 'attendance_mode', 'remote_days', 'is_operator',
        'exclude_from_attendance',
        // Personal settings:
        'theme', 'notification_prefs', 'date_format', 'week_start',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'string',
        'skills'             => 'array',
        'two_factor_enabled' => 'boolean',
        'date_of_birth'      => 'date',
        'hire_date'          => 'date',
        'last_login_at'      => 'datetime',
        'salary'             => 'decimal:2',
        'joined_at'          => 'date',
        'employee_status'    => 'string',
        'invitation_sent_at' => 'datetime',
        'invitation_expires_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'must_change_password' => 'boolean',
        'use_custom_timezone' => 'boolean',
        'forms_last_seen_at' => 'datetime',
        'is_operator' => 'boolean',
        'exclude_from_attendance' => 'boolean',
        'notification_prefs' => 'array',
        'remote_days' => 'array',
    ];

    /**
     * Whether this user wants a given notification category on a given channel
     * ('mail' or 'database'). Opt-out model: enabled unless explicitly turned
     * off. Mandatory-email categories can never be silenced on email.
     */
    public function wantsNotification(string $category, string $channel): bool
    {
        if ($channel === 'mail' && in_array($category, \App\Support\NotificationCategories::MANDATORY_EMAIL, true)) {
            return true;
        }

        $prefs = $this->notification_prefs ?? [];

        return (bool) ($prefs[$category][$channel] ?? true);
    }

    /** SaaS platform operator (can manage all tenants). */
    public function isOperator(): bool
    {
        return (bool) $this->is_operator;
    }

    /**
     * user_ids hidden from every attendance sheet / report. Attendance code is
     * keyed by user_id, so this is the single source of truth for "don't show
     * this person on any attendance surface". Tenant-scoped automatically.
     */
    public static function attendanceHiddenIds(): \Illuminate\Support\Collection
    {
        return static::where('exclude_from_attendance', true)->pluck('id');
    }

    protected $hidden = ['password', 'remember_token', 'salary'];

    protected $appends = ['role'];

    // Relationships
    public function company() { return $this->belongsTo(Company::class); }

    /**
     * The company entity (legal entity) this employee belongs to.
     * Source of the employee's default timezone and date/time display formats.
     */
    public function companyEntity() {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    /**
     * The job location (where this employee works) chosen from the central list.
     */
    public function jobLocation() {
        return $this->belongsTo(JobLocation::class, 'job_location_id');
    }

    /**
     * Relationship with manager (User).
     */
    public function manager() {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Self-referencing relationship for direct reports.
     */
    public function directReports() {
        return $this->hasMany(User::class, 'manager_id');
    }

    /** Additional managers of this employee (beyond the primary manager_id). */
    public function additionalManagers() {
        return $this->belongsToMany(User::class, 'employee_manager', 'user_id', 'manager_id')->withTimestamps();
    }

    /** Employees this user manages via the additional-managers pivot. */
    public function additionalTeam() {
        return $this->belongsToMany(User::class, 'employee_manager', 'manager_id', 'user_id')->withTimestamps();
    }

    /** Every manager of this employee: the primary + any additional (unique ids). */
    public function allManagerIds(): \Illuminate\Support\Collection
    {
        return collect([$this->manager_id])->filter()
            ->merge($this->additionalManagers()->pluck('users.id'))
            ->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** All employees this user manages: direct reports (primary) + pivot-managed (unique ids). */
    public function teamMemberIds(): \Illuminate\Support\Collection
    {
        return $this->directReports()->pluck('id')
            ->merge($this->additionalTeam()->pluck('users.id'))
            ->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * Can this user manage the given employee for permission purposes — i.e. is
     * a role-Manager AND the employee's primary/additional manager? An Employee
     * set as someone's manager returns false (manager tools are role-gated).
     */
    public function managesUser($employeeId): bool
    {
        if (!$this->hasRole(Role::MANAGER)) {
            return false;
        }

        return $this->teamMemberIds()->contains((int) $employeeId);
    }

    /** All employees this user manages (User models): direct reports + additional-managed. */
    public function teamMembers()
    {
        return User::whereIn('id', $this->teamMemberIds()->all())->get();
    }

    /**
     * Relationship with roles (many-to-many).
     */
    public function roles() {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('assigned_by', 'assigned_at');
    }

    /**
     * Relationship with Department (one-to-many belongsTo).
     */
    public function department() {
        return $this->belongsTo(Department::class);
    }

    // Dynamic role attribute for legacy / frontend compatibility
    public function getRoleAttribute() {
        $role = $this->roles->first();
        if (!$role) {
            return null;
        }

        $permissions = [];
        if ($role->relationLoaded('permissions')) {
            foreach ($role->permissions as $permission) {
                $permissions[$permission->slug] = true;
            }
        } else {
            foreach ($role->permissions()->get() as $permission) {
                $permissions[$permission->slug] = true;
            }
        }

        return (object)[
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permissions' => $permissions,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
        ];
    }

    public function departmentAssignments() {
        return $this->hasMany(UserDepartmentAssignment::class)
            ->with('department:id,name', 'location:id,name');
    }

    // Assigned jobs (jobs where this user is on the hiring team)
    public function assignedJobs() {
        return Job::whereHas('interviews', fn($q) => $q->where('interviewer_id', $this->id));
    }

    public function interviews() { return $this->hasMany(Interview::class, 'interviewer_id'); }

    public function activityLogs() {
        return $this->hasMany(ActivityLog::class)
            ->with('user:id,first_name,last_name')
            ->latest()
            ->limit(50);
    }

    // Access system helper methods

    /**
     * Determine if the user is an admin (super_admin or hr_admin).
     */
    public function isAdmin(): bool
    {
        return $this->hasRole([Role::SUPER_ADMIN, Role::HR_ADMIN]);
    }

    /**
     * Whether this user clocks in/out from the dashboard (remote) rather than
     * only on the biometric device. Defaults to biometric when unset.
     */
    public function usesDashboardClockIn(): bool
    {
        return $this->effectiveAttendanceMode(\Illuminate\Support\Carbon::today()) === 'remote';
    }

    /**
     * The attendance mode that actually applies on a given day — day-aware, so
     * hybrid / work-from-home is handled automatically:
     *   1. An approved Work-From-Home request covering the date → remote.
     *   2. The date's weekday is one of this employee's hybrid remote days → remote.
     *   3. Otherwise the employee's base attendance_mode (biometric / remote).
     */
    public function effectiveAttendanceMode(?\Illuminate\Support\Carbon $date = null): string
    {
        $date = $date ?: \Illuminate\Support\Carbon::today();

        // Company-wide WFH day (e.g. some Fridays) → everyone is remote.
        if (\App\Models\CompanyWfhDay::isCompanyRemote($date)) {
            return 'remote';
        }

        if ($this->isWorkingFromHomeOn($date)) {
            return 'remote';
        }

        $weekday = $date->format('D'); // Mon, Tue, …
        if (in_array($weekday, (array) ($this->remote_days ?? []), true)) {
            return 'remote';
        }

        return $this->attendance_mode ?? 'biometric';
    }

    /** True if the employee has an approved Work-From-Home request covering the date. */
    public function isWorkingFromHomeOn(\Illuminate\Support\Carbon $date): bool
    {
        return \App\Models\TimeOffRequest::where('user_id', $this->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->whereHas('policy', fn ($q) => $q->where(fn ($q2) => $q2
                ->whereRaw('LOWER(name) like ?', ['%work from home%'])
                ->orWhereRaw('LOWER(name) like ?', ['%wfh%'])))
            ->exists();
    }

    /**
     * Determine if the user is a manager. Manager access is granted STRICTLY by
     * the RBAC role — being set as someone's manager (manager_id / additional
     * managers) does NOT by itself grant manager tools. The reporting
     * relationship only decides WHOSE team a role-manager sees.
     */
    public function isManager(): bool
    {
        return $this->hasRole(Role::MANAGER);
    }

    /**
     * Check if a target user is in the reporting line of this user.
     */
    public function canManage(User $target): bool
    {
        if ($target->id === $this->id) {
            return false;
        }

        // Manager capabilities are role-gated: only a role-Manager (admins are
        // checked separately at call sites) can manage their reporting line.
        if (!$this->hasRole(Role::MANAGER)) {
            return false;
        }

        // Direct primary or additional manager.
        if ((int) $target->manager_id === (int) $this->id) {
            return true;
        }
        if ($this->additionalTeam()->whereKey($target->id)->exists()) {
            return true;
        }

        $current = $target;
        $visited = [];
        while ($current->manager_id !== null && !in_array($current->manager_id, $visited)) {
            if ($current->manager_id === $this->id) {
                return true;
            }
            $visited[] = $current->manager_id;
            $current = $current->manager;
            if (!$current) {
                break;
            }
        }

        return false;
    }

    /**
     * Get the authorization access scope for the user.
     * Returns: 'all' | 'department' | 'team' | 'self'
     */
    public function getAccessScope(): string
    {
        if ($this->isAdmin()) {
            return 'all';
        }

        if ($this->hasRole(Role::MANAGER)) {
            return 'team';
        }

        // Being someone's manager without the Manager role grants no elevated
        // scope — access is strictly by RBAC role.
        return 'self';
    }

    public function isSuperAdmin() { 
        return $this->roles()->where('slug', 'super_admin')->exists();
    }

    public function getFullNameAttribute(): string {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getInitialsAttribute(): string {
        return strtoupper(substr($this->first_name,0,1) . substr($this->last_name,0,1));
    }

    // --- Time Off Relationships ---

    public function timeOffRequests() {
        return $this->hasMany(TimeOffRequest::class);
    }

    public function timeOffBalances() {
        return $this->hasMany(TimeOffBalance::class);
    }

    public function timeOffPolicies() {
        return $this->belongsToMany(TimeOffPolicy::class, 'policy_user', 'user_id', 'policy_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    /** Company forms this user has been granted access to review. */
    public function reviewableForms() {
        return $this->belongsToMany(CompanyForm::class, 'form_reviewers', 'user_id', 'form_id')
            ->withTimestamps();
    }

    // --- HR Profile Template Relationships ---

    public function profileTemplates() {
        return $this->belongsToMany(ProfileTemplate::class, 'employee_profile_templates', 'user_id', 'template_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    public function fieldValues() {
        return $this->hasMany(EmployeeFieldValue::class, 'user_id');
    }

    public function getAllProfileFields() {
        // Default template is global (applies to everyone); dynamic templates only
        // count if explicitly assigned to this employee.
        $default = ProfileTemplate::active()->where('type', 'default')->with('sections.fields')->get();
        $assigned = $this->profileTemplates()->where('type', 'dynamic')->with('sections.fields')->get();
        $templates = $default->merge($assigned);
        $fields = collect();
        foreach ($templates as $template) {
            foreach ($template->sections as $section) {
                foreach ($section->fields as $field) {
                    $fields->push($field);
                }
            }
        }
        return $fields;
    }

    public function getFieldValue(string $key) {
        if ($this->isFillable($key) || array_key_exists($key, $this->getAttributes())) {
            return $this->getAttribute($key);
        }

        $field = $this->getAllProfileFields()->firstWhere('key', $key);
        if ($field) {
            $valueModel = $this->fieldValues->firstWhere('field_id', $field->id);
            return $valueModel ? $valueModel->getDisplayValue() : null;
        }

        // Fallback: well-known document-template tokens (e.g. [candidate], [job],
        // [today_date], [start_date]) that aren't backed by a column or profile
        // field. Only reached when nothing above matched, so real fields win.
        return $this->resolveDocumentToken($key);
    }

    /** Resolve common contract/letter placeholder tokens to real values. */
    public function resolveDocumentToken(string $key) {
        return match (strtolower(trim($key))) {
            'candidate', 'employee', 'employee_name', 'full_name', 'name' => $this->full_name,
            'job', 'position', 'designation', 'role', 'title' => $this->job_title,
            'department', 'dept' => optional($this->department)->name,
            'today_date', 'today', 'date', 'agreement_date', 'date_of_agreement', 'signing_date' => now()->format('d M Y'),
            'start_date', 'commencement_date', 'date_of_commencement', 'joining_date', 'join_date' => $this->documentStartDate(),
            'email', 'work_email' => $this->email,
            'cnic', 'cnic_number', 'cnic_no', 'national_id', 'nic' => $this->getFieldValue('cnic_number'),
            default => null,
        };
    }

    private function documentStartDate(): ?string {
        $raw = $this->joined_at ?? $this->hire_date ?? null;
        if (!$raw) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $raw;
        }
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'user_id')->latest();
    }

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    /**
     * When this employee first started — the EARLIEST valid date across every
     * source (a stray recent date can never shorten real tenure).
     */
    public function serviceStartDate(): ?\Carbon\Carbon
    {
        $gf = fn ($k) => method_exists($this, 'getFieldValue') ? $this->getFieldValue($k) : null;
        $candidates = [$this->hire_date, $gf('hire_date'), $gf('start_date'), $gf('date_of_commencement'), $this->joined_at];

        $start = null;
        foreach ($candidates as $c) {
            if ($c === null || $c === '') {
                continue;
            }
            try {
                $d = \Carbon\Carbon::parse($c);
            } catch (\Throwable $e) {
                continue;
            }
            if ($start === null || $d->lt($start)) {
                $start = $d;
            }
        }

        return $start;
    }

    /** Whole months of completed service, or null if no start date is on file. */
    public function monthsOfService(): ?int
    {
        $start = $this->serviceStartDate();

        return $start ? (int) abs($start->diffInMonths(\Carbon\Carbon::today())) : null;
    }

    public function payReviews()
    {
        return $this->hasMany(PayReview::class, 'user_id');
    }

    public function probations()
    {
        return $this->hasMany(Probation::class, 'user_id');
    }

    public function formSubmissions()
    {
        return $this->hasMany(FormSubmission::class, 'user_id');
    }

    public function policyAcknowledgments()
    {
        return $this->hasMany(PolicyAcknowledgment::class, 'user_id');
    }

    public function officeLocations()
    {
        return $this->belongsToMany(OfficeLocation::class, 'employee_office_locations')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function hasZktecoMapping(): bool
    {
        return $this->zkteco_uid !== null;
    }

    public function primaryOfficeLocation()
    {
        return $this->officeLocations()->wherePivot('is_primary', true)->first();
    }

    public function shiftAssignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    // --- Invitation Relationships & Methods ---

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function invitationLogs()
    {
        return $this->hasMany(InvitationLog::class);
    }

    public function isInvited(): bool
    {
        return $this->account_status === 'invited';
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function hasActiveInvitation(): bool
    {
        return $this->isInvited() && $this->invitation_expires_at && $this->invitation_expires_at->isFuture();
    }

    public function hasExpiredInvitation(): bool
    {
        return $this->isInvited() && $this->invitation_expires_at && $this->invitation_expires_at->isPast();
    }

    public function isPendingSetup(): bool
    {
        return $this->isInvited() && is_null($this->invitation_accepted_at);
    }

    public function scopeInvited($query)
    {
        return $query->where('account_status', 'invited');
    }

    public function scopeActive($query)
    {
        return $query->where('account_status', 'active');
    }

    public function scopePendingInvitation($query)
    {
        return $query->where('account_status', 'invited')->whereNull('invitation_accepted_at');
    }
}
