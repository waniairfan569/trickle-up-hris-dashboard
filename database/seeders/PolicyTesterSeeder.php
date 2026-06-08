<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\CompanyEntity;
use App\Http\Controllers\TimeOffController;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PolicyTesterSeeder extends Seeder
{
    public function run()
    {
        // 1. Delete all non-admin users and employees
        $admins = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['super_admin', 'hr_admin']);
        })->pluck('id')->toArray();

        User::whereNotIn('id', $admins)->delete();
        Employee::whereNotIn('user_id', $admins)->delete();

        // Ensure we have a company entity
        $company = CompanyEntity::first();
        if (!$company) {
            echo "No company entity found.\n";
            return;
        }

        $password = Hash::make('password');

        $testProfiles = [
            [
                'first_name' => 'John',
                'last_name' => 'SixMonths',
                'email' => 'john.6mo@company.com',
                'gender' => 'Male',
                'hire_date' => Carbon::now()->subMonths(8), // 8 months service -> 5 days Paternity
            ],
            [
                'first_name' => 'Mike',
                'last_name' => 'OneYear',
                'email' => 'mike.1yr@company.com',
                'gender' => 'Male',
                'hire_date' => Carbon::now()->subYears(2), // 2 years service -> 10 days Paternity
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'OneYear',
                'email' => 'sarah.1yr@company.com',
                'gender' => 'Female',
                'hire_date' => Carbon::now()->subYears(2), // 2 years service -> Maternity
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'NewHire',
                'email' => 'emily.new@company.com',
                'gender' => 'Female',
                'hire_date' => Carbon::now()->subDays(10), // 10 days service -> No maternity yet
            ]
        ];

        $timeOffController = new TimeOffController(
            new \App\Services\TimeOffBalanceService()
        );

        foreach ($testProfiles as $profile) {
            $user = User::create([
                'company_id' => 1,
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'email' => $profile['email'],
                'password' => $password,
                'status' => 'active',
                'account_status' => 'active',
                'must_change_password' => false,
                'gender' => $profile['gender'],
                'hire_date' => $profile['hire_date'],
            ]);

            $user->roles()->attach(4);

            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => 1,
                'first_name' => $profile['first_name'],
                'last_name' => $profile['last_name'],
                'email' => $profile['email'],
                'job_title' => 'QA Tester',
                'employment_type' => 'full_time',
                'hire_date' => $profile['hire_date'],
                'status' => 'active'
            ]);

            // Assign policies using the controller logic (auth user will be null, so it falls back)
            // Wait, our controller method aborts if user is not admin.
            // Let's replicate the policy assignment logic here to avoid auth issues in console.
            $policies = \App\Models\TimeOffPolicy::all();
            $year = now()->year;
            $monthsOfService = $profile['hire_date']->diffInMonths(now());
            
            foreach ($policies as $policy) {
                $shouldAssign = false;
                $balanceToAdd = $policy->days_per_year;
                
                if (str_contains($policy->name, 'Paternity')) {
                    if ($profile['gender'] === 'Male') {
                        if (str_contains($policy->name, '5 Days') && $monthsOfService >= 6 && $monthsOfService < 12) {
                            $shouldAssign = true;
                        } elseif (str_contains($policy->name, '10 Days') && $monthsOfService >= 12) {
                            $shouldAssign = true;
                        }
                    }
                } elseif (str_contains($policy->name, 'Maternity')) {
                    if ($profile['gender'] === 'Female' && $monthsOfService >= 12) {
                        $shouldAssign = true;
                    }
                } else {
                    $shouldAssign = true;
                }

                if ($shouldAssign) {
                    if (!$user->timeOffPolicies()->where('time_off_policies.id', $policy->id)->exists()) {
                        $user->timeOffPolicies()->attach($policy->id, [
                            'assigned_by' => \App\Models\User::first()->id,
                            'assigned_at' => now(),
                        ]);
                    }
                    
                    $balanceService = app(\App\Services\TimeOffBalanceService::class);
                    $balance = $balanceService->getOrCreateBalance($user, $policy, $year);
                    $balance->update(['opening_balance' => $balanceToAdd]);
                }
            }
        }
        
        echo "Test employees created successfully.\n";
    }
}
