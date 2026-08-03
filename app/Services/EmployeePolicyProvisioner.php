<?php

namespace App\Services;

use App\Models\PolicyAcknowledgment;
use App\Models\PolicyAssignment;
use App\Models\User;
use App\Notifications\PolicyAssigned;
use Illuminate\Support\Facades\Log;

/**
 * Gives a newly-created employee the company (acknowledgment) policies that
 * already exist — every policy targeting the whole company, plus any targeting
 * their department — so a new hire inherits them without an admin re-assigning.
 *
 * NOTE: Time-off (leave) policies + balances and the default shift are already
 * auto-assigned on user creation by the User::created hook, and maternity /
 * paternity eligibility is enforced when leave is actually requested — so this
 * service intentionally covers company policies only.
 */
class EmployeePolicyProvisioner
{
    /**
     * Run every auto-assignment for a freshly-created employee.
     *
     * @return array{company:int}
     */
    public function provisionForNewEmployee(User $employee, ?User $actor = null): array
    {
        $company = 0;

        try {
            $company = $this->assignCompanyPolicies($employee);
        } catch (\Throwable $e) {
            Log::warning("Auto-assign company policies failed for {$employee->email}: " . $e->getMessage());
        }

        return ['company' => $company];
    }

    /**
     * Give the employee a pending acknowledgment for every company policy that
     * targets the whole company or their department. Idempotent. Returns the
     * number of acknowledgments newly created.
     */
    public function assignCompanyPolicies(User $employee, bool $notify = true): int
    {
        $deptId = $employee->department_id;

        $assignments = PolicyAssignment::where(function ($q) use ($deptId) {
            $q->where('assigned_to_type', 'all');
            if ($deptId) {
                $q->orWhere(function ($q2) use ($deptId) {
                    $q2->where('assigned_to_type', 'department')->where('assigned_to_id', $deptId);
                });
            }
        })->get();

        $added = 0;
        $notified = [];

        foreach ($assignments as $assignment) {
            $ack = PolicyAcknowledgment::firstOrCreate(
                ['policy_id' => $assignment->policy_id, 'user_id' => $employee->id],
                ['assignment_id' => $assignment->id, 'status' => 'pending']
            );

            if (!$ack->wasRecentlyCreated) {
                continue;
            }
            $added++;

            // One in-app notice per policy (a policy may match more than one assignment).
            if ($notify && !isset($notified[$assignment->policy_id]) && $assignment->policy) {
                $notified[$assignment->policy_id] = true;
                try {
                    $employee->notify(new PolicyAssigned($assignment->policy));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $added;
    }
}
