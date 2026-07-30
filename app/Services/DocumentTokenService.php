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

        // Every profile field defined for this employee is ALSO a token — by its
        // display name AND its key — so a newly added field (e.g. a Compensation
        // field like "Revised Gross Monthly Salary") works in documents with no
        // code change. The hardcoded concepts above win on any shared spelling.
        foreach ($subject->getAllProfileFields() as $field) {
            $value = $subject->getFieldValue($field->key);
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }
            foreach (['[' . $field->name . ']', '[' . $field->key . ']'] as $spelling) {
                if (!isset($tokens[$spelling])) {
                    $tokens[$spelling] = (string) $value;
                }
            }
        }

        return $tokens;
    }

    /**
     * The catalog of tokens an admin can drop into a document, grouped for a
     * copy-me reference. Built from the standard fields PLUS every profile field
     * (grouped by its section), so any field added to the profile shows up here
     * automatically. No subject needed — this lists what's *available*, not one
     * person's values.
     */
    public function availableTokens(): array
    {
        $groups = [
            'General' => [
                ['label' => 'Employee name', 'token' => '[Employee Name]'],
                ['label' => 'Designation / job title', 'token' => '[Designation]'],
                ['label' => 'Department', 'token' => '[Department]'],
                ['label' => 'Work email', 'token' => '[Email]'],
                ['label' => 'CNIC', 'token' => '[CNIC]'],
                ['label' => "Today's date", 'token' => '[Agreement Date]'],
                ['label' => 'Joining date', 'token' => '[Joining Date]'],
            ],
        ];

        $sections = \App\Models\ProfileSection::with(['fields' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')->get();

        $seen = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section->fields as $field) {
                if (isset($seen[$field->key])) {
                    continue;
                }
                $seen[$field->key] = true;
                $items[] = ['label' => $field->name, 'token' => '[' . $field->name . ']'];
            }
            if (!empty($items)) {
                $name = $section->name ?: 'Fields';
                $groups[$name] = array_merge($groups[$name] ?? [], $items);
            }
        }

        $groups['Signatures'] = [
            ['label' => 'Employee signature', 'token' => '[Employee Signature]'],
            ['label' => 'Company signature', 'token' => '[Company Signature]'],
        ];

        return $groups;
    }
}
