    public function assignDefaultPolicies(\App\Models\User $employee)
    {
        $user = auth()->user() ?? \App\Models\User::first();

        if (!$user->hasRole('super_admin') && !$user->hasRole('hr_admin')) {
            abort(403, 'Unauthorized to assign default policies.');
        }

        $policies = \App\Models\TimeOffPolicy::all();
        $year = now()->year;
        
        $hireDate = $employee->hire_date ? \Carbon\Carbon::parse($employee->hire_date) : now();
        $monthsOfService = $hireDate->diffInMonths(now());
        
        foreach ($policies as $policy) {
            $shouldAssign = false;
            $balanceToAdd = $policy->days_per_year;
            
            if (str_contains($policy->name, 'Paternity')) {
                if ($employee->gender === 'male' || $employee->gender === 'Male') {
                    if (str_contains($policy->name, '5 Days') && $monthsOfService >= 6 && $monthsOfService < 12) {
                        $shouldAssign = true;
                    } elseif (str_contains($policy->name, '10 Days') && $monthsOfService >= 12) {
                        $shouldAssign = true;
                    }
                }
            } elseif (str_contains($policy->name, 'Maternity')) {
                if (($employee->gender === 'female' || $employee->gender === 'Female') && $monthsOfService >= 12) {
                    $shouldAssign = true;
                }
            } else {
                $shouldAssign = true;
            }

            if ($shouldAssign) {
                // Attach the policy if not attached
                if (!$employee->timeOffPolicies()->where('time_off_policies.id', $policy->id)->exists()) {
                    $employee->timeOffPolicies()->attach($policy->id, [
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                    ]);
                }
                
                // Set the balance
                $balance = $this->balanceService->getOrCreateBalance($employee, $policy, $year);
                $balance->update(['balance' => $balanceToAdd]);
            }
        }

        return back()->with('success', 'Default leave policies assigned based on tenure and gender.');
    }
