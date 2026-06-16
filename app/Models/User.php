<?php
namespace App\Models;

use App\Traits\RoleChecker;
use App\Traits\HasHRAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
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
        'must_change_password', 'invited_by',
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
    ];

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
     * Determine if the user is a manager (has direct reports or has manager role).
     */
    public function isManager(): bool
    {
        return $this->directReports()->exists() || $this->hasRole(Role::MANAGER);
    }

    /**
     * Check if a target user is in the reporting line of this user.
     */
    public function canManage(User $target): bool
    {
        if ($target->id === $this->id) {
            return false;
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
            return 'department';
        }

        if ($this->directReports()->exists()) {
            return 'team';
        }

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
        if (!$field) {
            return null;
        }

        $valueModel = $this->fieldValues->firstWhere('field_id', $field->id);
        return $valueModel ? $valueModel->getDisplayValue() : null;
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'user_id')->latest();
    }

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function payReviews()
    {
        return $this->hasMany(PayReview::class, 'user_id');
    }

    public function probations()
    {
        return $this->hasMany(Probation::class, 'user_id');
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
