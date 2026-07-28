<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

/**
 * Resolves bracketed document tokens ([Employee Name], [CNIC], [Probation Start
 * Date], …) to a subject employee's real profile values. Used both when a
 * document is sent for signature AND when an employee simply reads/acknowledges
 * a company document — so the same tokens fill with whoever is viewing it.
 */
class DocumentTokenService
{
    /**
     * ['[Employee Name]' => 'Jane Doe', '[CNIC]' => '35202-…', …] for the given
     * employee. Only tokens with a known value are returned.
     */
    public function profileTokens(?User $subject): array
    {
        if (!$subject) {
            return [];
        }

        $fmtDate = function ($d) {
            if ($d === null || $d === '') {
                return null;
            }
            try {
                return Carbon::parse($d)->format('d M Y');
            } catch (\Throwable $e) {
                return (string) $d;
            }
        };

        $fullName   = $subject->full_name ?: $subject->getFieldValue('full_name');
        $jobTitle   = $subject->job_title ?: $subject->getFieldValue('job_title');
        $department = optional($subject->department)->name ?: $subject->getFieldValue('department');
        $email      = $subject->email ?: $subject->getFieldValue('email');
        $startDate  = $fmtDate($subject->getFieldValue('start_date')
            ?: $subject->getFieldValue('date_of_commencement')
            ?: $subject->hire_date
            ?: $subject->joined_at);
        $salaryRaw  = $subject->getFieldValue('salary') ?: $subject->salary;
        $salary     = ($salaryRaw !== null && $salaryRaw !== '') ? number_format((float) $salaryRaw) : null;
        $probRaw    = $subject->getFieldValue('probation_salary');
        $probSalary = ($probRaw !== null && $probRaw !== '') ? number_format((float) $probRaw) : null;
        $cnic       = $subject->getFieldValue('cnic_number') ?: $subject->getFieldValue('cnic');
        $today      = now()->format('d M Y');

        // Probation window — latest probation record for this employee.
        $probation  = $subject->probations()->latest('start_date')->first();
        $probStart  = $probation ? $fmtDate($probation->start_date) : $fmtDate($subject->getFieldValue('probation_start_date'));
        $probEnd    = $probation ? $fmtDate($probation->end_date) : $fmtDate($subject->getFieldValue('probation_end_date'));

        // Each real value → every placeholder spelling a document might use, so
        // both natural ([Full Name]) and key ([full_name]) forms resolve.
        // Matching is case-sensitive, so capitalised variants are listed.
        $concepts = [
            [$fullName,   ['Full Name', 'Employee Name', 'Employee', 'Candidate', 'Name',
                           'full_name', 'employee_name', 'candidate', 'name']],
            [$jobTitle,   ['Job Title', 'Position', 'Designation', 'Role',
                           'job_title', 'job', 'position', 'designation', 'title']],
            [$department, ['Department', 'department', 'dept']],
            [$email,      ['Email', 'Work Email', 'email', 'work_email']],
            [$today,      ['Date', 'Date of Agreement', 'Agreement Date', 'Today',
                           'date', 'today_date', 'today', 'agreement_date', 'date_of_agreement', 'signing_date']],
            [$startDate,  ['Start Date', 'Date of Commencement', 'Commencement Date', 'Joining Date',
                           'start_date', 'commencement_date', 'date_of_commencement', 'joining_date']],
            [$salary,     ['Amount', 'Salary', 'amount', 'salary']],
            [$probSalary, ['Probation Salary', 'Probation Amount', 'Salary During Probation',
                           'probation_salary', 'probation_amount']],
            [$probStart,  ['Probation Start Date', 'Probation Start', 'Probation Period Start',
                           'probation_start_date', 'probation_start']],
            [$probEnd,    ['Probation End Date', 'Probation End', 'Probation Period End', 'End of Probation',
                           'probation_end_date', 'probation_end']],
            [$cnic,       ['CNIC', 'CNIC Number', 'CNIC No', 'National ID', 'ID Number', 'NIC',
                           'cnic', 'cnic_number', 'cnic_no', 'national_id']],
        ];

        $tokens = [];
        foreach ($concepts as [$value, $names]) {
            if ($value === null || $value === '') {
                continue;
            }
            foreach ($names as $name) {
                $tokens['[' . $name . ']'] = (string) $value;
            }
        }

        return $tokens;
    }
}
