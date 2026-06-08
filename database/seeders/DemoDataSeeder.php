<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        
        // 1. Create 10 employees
        $employeeIds = [];
        for ($i = 0; $i < 10; $i++) {
            $employeeIds[] = DB::table('employees')->insertGetId([
                'company_id' => 1,
                'department_id' => $faker->numberBetween(1, 5),
                'location_id' => $faker->numberBetween(1, 3),
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'employee_id' => 'EMP-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'job_title' => $faker->jobTitle,
                'employment_type' => $faker->randomElement(['full_time', 'part_time', 'contract']),
                'hire_date' => $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                'status' => 'active',
                'salary' => $faker->randomFloat(2, 40000, 150000),
                'currency' => 'USD',
                'annual_leave_days' => 20.0,
                'used_annual_days' => $faker->randomFloat(1, 0, 15),
                'sick_leave_days' => 10.0,
                'used_sick_days' => $faker->randomFloat(1, 0, 5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }



        // 6. Create Time-Off Policies
        $annualPolicyId = DB::table('time_off_policies')->insertGetId([
            'name' => 'Annual Leave 2026',
            'type' => 'annual',
            'accrual_rate' => 0,
            'max_balance' => 20,
            'carry_over' => true,
            'applies_to_all' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $sickPolicyId = DB::table('time_off_policies')->insertGetId([
            'name' => 'Standard Sick Leave',
            'type' => 'sick',
            'accrual_rate' => 0,
            'max_balance' => 10,
            'carry_over' => false,
            'applies_to_all' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 7. Map employees to users so we can test User-centric relationships
        $userIds = [];
        foreach ($employeeIds as $index => $empId) {
            $userId = DB::table('users')->insertGetId([
                'company_id' => 1,
                'first_name' => 'Demo',
                'last_name' => 'User ' . $index,
                'email' => 'demo' . $index . '@company.com',
                'password' => bcrypt('password'),
                'account_status' => 'active',
                'must_change_password' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userIds[] = $userId;
            DB::table('employees')->where('id', $empId)->update(['user_id' => $userId]);

            // Assign balances
            DB::table('time_off_balances')->insert([
                ['user_id' => $userId, 'policy_id' => $annualPolicyId, 'year' => 2026, 'balance' => 20, 'used' => 0, 'pending' => 0, 'created_at' => now()],
                ['user_id' => $userId, 'policy_id' => $sickPolicyId, 'year' => 2026, 'balance' => 10, 'used' => 0, 'pending' => 0, 'created_at' => now()]
            ]);
        }

        // 8. Create 15 Time-Off Requests
        $statuses = ['pending', 'approved', 'rejected', 'cancelled'];
        for ($i = 0; $i < 15; $i++) {
            $status = $faker->randomElement($statuses);
            
            DB::table('time_off_requests')->insert([
                'user_id' => $faker->randomElement($userIds),
                'policy_id' => $faker->randomElement([$annualPolicyId, $sickPolicyId]),
                'start_date' => $faker->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
                'end_date' => $faker->dateTimeBetween('+2 months', '+3 months')->format('Y-m-d'),
                'days_requested' => $faker->randomFloat(1, 1, 5),
                'status' => $status,
                'reason' => $faker->sentence(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 9. Create a Performance Review Cycle
        $cycleId = DB::table('review_cycles')->insertGetId([
            'name' => 'Q2 2026 Performance Review',
            'start_date' => now()->startOfMonth()->format('Y-m-d'),
            'end_date' => now()->addMonth()->endOfMonth()->format('Y-m-d'),
            'status' => 'active',
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed some sample reviews
        foreach ($userIds as $userId) {
            DB::table('performance_reviews')->insert([
                'cycle_id' => $cycleId,
                'reviewee_id' => $userId,
                'type' => 'self',
                'status' => 'draft',
                'content' => json_encode(['achievements' => 'Did great things.', 'goals' => 'Do more.', 'rating' => 4]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
