<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TimeOffPolicy;
use App\Models\CompanyEntity;

class DefaultLeavePoliciesSeeder extends Seeder
{
    public function run()
    {
        $company = CompanyEntity::first();
        if (!$company) return;

        $policies = [
            [
                'name' => 'Casual Leave',
                'description' => '12 Casual Leave days',
                'type' => 'custom', // Valid enum values: annual, sick, unpaid, custom
                'accrual_type' => 'annually', // Valid: none, monthly, annually
                'days_per_year' => 12,
                'requires_approval' => true,
                'approval_type' => 'manager',
                'allow_negative_balance' => false,
            ],
            [
                'name' => 'Annual Leave',
                'description' => '16 Annual Leave days',
                'type' => 'annual',
                'accrual_type' => 'annually',
                'days_per_year' => 16,
                'requires_approval' => true,
                'approval_type' => 'manager',
                'allow_negative_balance' => false,
            ],
            [
                'name' => 'Eid Leave',
                'description' => '6 Eid Leave days (maximum 3 days per Eid)',
                'type' => 'custom',
                'accrual_type' => 'annually',
                'days_per_year' => 6,
                'requires_approval' => true,
                'approval_type' => 'manager',
                'allow_negative_balance' => false,
            ],
            [
                'name' => 'Paternity Leave (5 Days)',
                'description' => '5 days paid leave (6 months+ service)',
                'type' => 'custom',
                'accrual_type' => 'annually',
                'days_per_year' => 5,
                'requires_approval' => true,
                'approval_type' => 'hr_admin',
                'allow_negative_balance' => false,
            ],
            [
                'name' => 'Paternity Leave (10 Days)',
                'description' => '10 days paid leave (1 year+ service)',
                'type' => 'custom',
                'accrual_type' => 'annually',
                'days_per_year' => 10,
                'requires_approval' => true,
                'approval_type' => 'hr_admin',
                'allow_negative_balance' => false,
            ],
            [
                'name' => 'Maternity Leave',
                'description' => '3 months paid leave (1 year+ service)',
                'type' => 'custom',
                'accrual_type' => 'annually',
                'days_per_year' => 90,
                'requires_approval' => true,
                'approval_type' => 'hr_admin',
                'allow_negative_balance' => false,
            ],
            [
                'name' => 'Unpaid Leave',
                'description' => 'Additional leave when exhausted',
                'type' => 'unpaid',
                'accrual_type' => 'annually',
                'days_per_year' => 0,
                'requires_approval' => true,
                'approval_type' => 'manager',
                'allow_negative_balance' => true,
            ]
        ];

        foreach ($policies as $p) {
            TimeOffPolicy::updateOrCreate(
                ['company_entity_id' => $company->id, 'name' => $p['name']],
                $p
            );
        }
    }
}
