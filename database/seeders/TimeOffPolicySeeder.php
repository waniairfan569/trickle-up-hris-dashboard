<?php

namespace Database\Seeders;

use App\Models\CompanyEntity;
use App\Models\TimeOffPolicy;
use App\Models\User;
use App\Services\TimeOffBalanceService;
use Illuminate\Database\Seeder;

class TimeOffPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(TimeOffBalanceService $balanceService): void
    {
        $entity = CompanyEntity::primary() ?? CompanyEntity::first();
        $entityId = $entity ? $entity->id : null;

        TimeOffPolicy::query()->forceDelete();

        // 1. Casual Leave
        TimeOffPolicy::create([
            'company_entity_id' => $entityId,
            'name' => 'Casual Leave',
            'description' => '12 Casual Leave days per year. If exhausted, treated as unpaid.',
            'type' => 'custom',
            'accrual_type' => 'none',
            'days_per_year' => 12.0,
            'max_balance' => 12.0,
            'carry_over' => false,
            'carry_over_max' => null,
            'requires_approval' => true,
            'approval_type' => 'manager',
            'min_notice_days' => 1,
            'allow_half_days' => true,
            'allow_negative_balance' => false,
            'is_paid' => true,
            'is_active' => true,
        ]);

        // 2. Annual Leave
        TimeOffPolicy::create([
            'company_entity_id' => $entityId,
            'name' => 'Annual Leave',
            'description' => '16 Annual Leave days per year. If exhausted, treated as unpaid.',
            'type' => 'annual',
            'accrual_type' => 'none',
            'days_per_year' => 16.0,
            'max_balance' => 16.0,
            'carry_over' => false,
            'carry_over_max' => null,
            'requires_approval' => true,
            'approval_type' => 'manager',
            'min_notice_days' => 3,
            'allow_half_days' => true,
            'allow_negative_balance' => false,
            'is_paid' => true,
            'is_active' => true,
        ]);

        // 3. Eid Leave
        TimeOffPolicy::create([
            'company_entity_id' => $entityId,
            'name' => 'Eid Leave',
            'description' => '6 Eid Leave days (max 3 days per Eid). Any additional Eid days taken will be unpaid.',
            'type' => 'custom',
            'accrual_type' => 'none',
            'days_per_year' => 6.0,
            'max_balance' => 6.0,
            'carry_over' => false,
            'carry_over_max' => null,
            'requires_approval' => true,
            'approval_type' => 'manager',
            'min_notice_days' => 7,
            'allow_half_days' => false,
            'allow_negative_balance' => false,
            'is_paid' => true,
            'is_active' => true,
        ]);

        // 4. Paternity Leave
        TimeOffPolicy::create([
            'company_entity_id' => $entityId,
            'name' => 'Paternity Leave',
            'description' => '5 days after 6 months service, 10 days after 1 year service.',
            'type' => 'paternity',
            'accrual_type' => 'none',
            'days_per_year' => 10.0, // max entitlement, assigned dynamically
            'max_balance' => 10.0,
            'carry_over' => false,
            'carry_over_max' => null,
            'requires_approval' => true,
            'approval_type' => 'manager',
            'min_notice_days' => 7,
            'allow_half_days' => false,
            'allow_negative_balance' => false,
            'is_paid' => true,
            'is_active' => true,
        ]);

        // 5. Maternity Leave
        TimeOffPolicy::create([
            'company_entity_id' => $entityId,
            'name' => 'Maternity Leave',
            'description' => '3 months paid leave (approx 90 days), eligible after 1 year of service.',
            'type' => 'maternity',
            'accrual_type' => 'none',
            'days_per_year' => 90.0,
            'max_balance' => 90.0,
            'carry_over' => false,
            'carry_over_max' => null,
            'requires_approval' => true,
            'approval_type' => 'hr_admin',
            'min_notice_days' => 30,
            'allow_half_days' => false,
            'allow_negative_balance' => false,
            'is_paid' => true,
            'is_active' => true,
        ]);

        // 6. Unpaid Leave
        TimeOffPolicy::create([
            'company_entity_id' => $entityId,
            'name' => 'Unpaid Leave',
            'description' => 'Used when casual, annual, or Eid leave is exhausted.',
            'type' => 'unpaid',
            'accrual_type' => 'none',
            'days_per_year' => 30.0, // Not strictly enforced, just a placeholder
            'max_balance' => null,
            'carry_over' => false,
            'carry_over_max' => null,
            'requires_approval' => true,
            'approval_type' => 'manager',
            'min_notice_days' => 1,
            'allow_half_days' => true,
            'allow_negative_balance' => true, // Can go negative if they take endless unpaid
            'is_paid' => false,
            'is_active' => true,
        ]);

        // Run auto-assignment for all existing users
        $users = User::all();
        $controller = new \App\Http\Controllers\TimeOffController($balanceService);

        foreach ($users as $user) {
            // Assign using the logic in the controller
            $controller->assignDefaultPolicies($user);
        }
    }
}
