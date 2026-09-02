<?php

namespace App\Tenancy;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TimeOffPolicy;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a brand-new agency (tenant): its organization, first admin, and a
 * sensible set of starter defaults so the workspace is usable immediately.
 */
class TenantProvisioner
{
    public function __construct(private TenantManager $tenants)
    {
    }

    /**
     * @param  array{company_name:string, first_name:string, last_name:string, email:string, password:string}  $data
     * @return array{0: Tenant, 1: User}
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'status' => 'trialing',
                'plan' => 'trial',
                'brand_name' => $data['company_name'],
                'trial_ends_at' => now()->addDays(14),
            ]);

            // Activate scoping so everything below is stamped with this tenant.
            $this->tenants->set($tenant);

            $company = Company::create(['name' => $data['company_name']]);

            $admin = new User([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'account_status' => 'active',
                'job_title' => 'Administrator',
                'hire_date' => now()->toDateString(),
                'joined_at' => now()->toDateString(),
            ]);
            $admin->company_id = $company->id;
            // Public self-signup: the owner must confirm their email before the
            // workspace is usable (opts out of the auto-verify default).
            $admin->requiresEmailVerification = true;
            $admin->email_verified_at = null;
            $admin->save();

            // Owner record — flagged is_system so it isn't counted as a real
            // employee (mirrors the existing account-owner pattern).
            Employee::create([
                'user_id' => $admin->id,
                'company_id' => $company->id,
                'is_system' => true,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
                'job_title' => 'Administrator',
                'employment_type' => 'full_time',
                'hire_date' => now()->toDateString(),
                'status' => 'active',
            ]);

            // Roles are global; grant the owner super admin.
            $superAdminId = Role::where('slug', Role::SUPER_ADMIN)->value('id');
            if ($superAdminId) {
                $admin->roles()->attach($superAdminId, ['assigned_at' => now()]);
            }

            $this->seedDefaults();

            return [$tenant, $admin];
        });
    }

    private function seedDefaults(): void
    {
        WorkSchedule::create([
            'name' => 'Full-time schedule',
            'description' => 'Monday to Friday, 9:30 AM - 6:00 PM',
            'hours_per_day' => 8.5,
            'days_per_week' => 5,
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'start_time' => '09:30',
            'end_time' => '18:00',
            'is_default' => true,
            'is_active' => true,
        ]);

        $policies = [
            ['name' => 'Planned Leaves', 'type' => 'annual', 'days_per_year' => 16],
            ['name' => 'Unplanned Leaves', 'type' => 'custom', 'days_per_year' => 12],
            ['name' => 'Sick Leave', 'type' => 'sick', 'days_per_year' => 10],
        ];
        foreach ($policies as $p) {
            TimeOffPolicy::create([
                'name' => $p['name'],
                'type' => $p['type'],
                'description' => $p['name'] . ' policy',
                'accrual_type' => 'annually',
                'days_per_year' => $p['days_per_year'],
                'requires_approval' => true,
                'approval_type' => 'manager',
                'allow_half_days' => true,
                'is_paid' => true,
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'agency';
        $slug = $base;
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
