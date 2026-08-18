<?php

use App\Models\HrDocumentTemplate;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the two built-in HR document templates (Lateness Review, Return to Work)
 * for every existing tenant. Idempotent: re-running refreshes the schema of the
 * system templates without touching admin-created ones or filled documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            [
                'name'        => 'Lateness Review',
                'subtitle'    => 'Attendance — lateness review discussion',
                'description' => 'Record and discuss an employee’s lateness for the month, with each late date listed, next steps agreed, and signatures.',
                'icon'        => 'alarm-clock',
                'prefill'     => 'lateness',
                'schema'      => $this->latenessSchema(),
            ],
            [
                'name'        => 'Return to Work Form',
                'subtitle'    => 'To be completed at the earliest opportunity upon return from unplanned absence',
                'description' => 'Complete on an employee’s return from unplanned absence: reason, reporting process, and acknowledgement.',
                'icon'        => 'clipboard-check',
                'prefill'     => 'absence',
                'schema'      => $this->returnToWorkSchema(),
            ],
        ];

        // One copy per tenant (single-tenant installs get exactly one set).
        $tenantIds = Tenant::query()->pluck('id')->all();
        if (empty($tenantIds)) {
            $tenantIds = [null];
        }

        foreach ($tenantIds as $tenantId) {
            foreach ($templates as $i => $tpl) {
                $existing = HrDocumentTemplate::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $tpl['name'])
                    ->where('is_system', true)
                    ->first() ?? new HrDocumentTemplate;

                $existing->forceFill(array_merge($tpl, [
                    'tenant_id'  => $tenantId,
                    'is_system'  => true,
                    'is_active'  => true,
                    'sort_order' => $i,
                    'schema'     => $tpl['schema'],
                ]))->save();
            }
        }
    }

    public function down(): void
    {
        HrDocumentTemplate::withoutGlobalScopes()
            ->where('is_system', true)
            ->whereIn('name', ['Lateness Review', 'Return to Work Form'])
            ->forceDelete();
    }

    private function latenessSchema(): array
    {
        return [
            [
                'title'  => 'Section 1 — Employee Details',
                'fields' => [
                    ['id' => 'employee_name', 'label' => 'Employee Name', 'type' => 'text', 'width' => 'half', 'prefill' => 'employee_name'],
                    ['id' => 'job_title', 'label' => 'Job Title', 'type' => 'text', 'width' => 'half', 'prefill' => 'job_title'],
                    ['id' => 'department', 'label' => 'Department', 'type' => 'text', 'width' => 'half', 'prefill' => 'department'],
                    ['id' => 'line_manager', 'label' => 'Line Manager', 'type' => 'text', 'width' => 'half', 'prefill' => 'line_manager'],
                    ['id' => 'date_of_lateness', 'label' => 'Date of lateness', 'type' => 'text', 'width' => 'half'],
                    ['id' => 'date_of_meeting', 'label' => 'Date of meeting', 'type' => 'date', 'width' => 'half'],
                    ['id' => 'total_late_days', 'label' => 'Total Days of lateness this month', 'type' => 'text', 'width' => 'half', 'prefill' => 'late_count'],
                    ['id' => 'aware_policy', 'label' => 'Aware of lateness policy', 'type' => 'radio', 'width' => 'half', 'options' => ['Yes', 'No']],
                ],
            ],
            [
                'title'  => 'Section 2 — Lateness Detail',
                'fields' => [
                    ['id' => 'lateness_detail', 'label' => 'Reason for Lateness (each late date listed separately)', 'type' => 'table', 'width' => 'full', 'columns' => ['Date', 'Reason'], 'prefill' => 'lateness_table'],
                    ['id' => 'lateness_reporting', 'label' => 'Lateness reporting', 'type' => 'radio', 'width' => 'full', 'options' => ['Reported to team', 'Did not report to team']],
                ],
            ],
            [
                'title'  => 'Section 3 — Next Steps',
                'fields' => [
                    ['id' => 'steps', 'label' => 'What steps are you taking to avoid further instances of lateness?', 'type' => 'textarea', 'width' => 'full'],
                    ['id' => 'support', 'label' => 'How can Trickle Up support you to arrive on time?', 'type' => 'textarea', 'width' => 'full'],
                    ['id' => 'policy_note', 'label' => '', 'type' => 'note', 'width' => 'full', 'text' => 'Policy reminder: All colleagues are to arrive by their shift start time and ensure they are ready to work within 15 minutes of their shift start time. Continued lateness will be managed as per our conduct & capability process.'],
                ],
            ],
            [
                'title'  => 'Section 4 — Acknowledgement & Signatures',
                'fields' => [
                    ['id' => 'ack_note', 'label' => '', 'type' => 'note', 'width' => 'full', 'text' => 'By signing below the employee confirms they have read, understood, and agreed to the contents of this lateness review discussion.'],
                    ['id' => 'employee_signature', 'label' => 'Employee', 'type' => 'signature', 'width' => 'half'],
                    ['id' => 'manager_signature', 'label' => 'Operations & People Manager', 'type' => 'signature', 'width' => 'half'],
                ],
            ],
        ];
    }

    private function returnToWorkSchema(): array
    {
        return [
            [
                'title'  => 'Section 1 — Employee Details',
                'fields' => [
                    ['id' => 'employee_name', 'label' => 'Employee Name', 'type' => 'text', 'width' => 'half', 'prefill' => 'employee_name'],
                    ['id' => 'job_title', 'label' => 'Job Title', 'type' => 'text', 'width' => 'half', 'prefill' => 'job_title'],
                    ['id' => 'department', 'label' => 'Department', 'type' => 'text', 'width' => 'half', 'prefill' => 'department'],
                    ['id' => 'line_manager', 'label' => 'Line Manager', 'type' => 'text', 'width' => 'half', 'prefill' => 'line_manager'],
                    ['id' => 'date_of_absence', 'label' => 'Date of Absence', 'type' => 'date', 'width' => 'half'],
                    ['id' => 'date_of_return', 'label' => 'Date of Return', 'type' => 'date', 'width' => 'half'],
                    ['id' => 'total_days_absent', 'label' => 'Total Days Absent', 'type' => 'text', 'width' => 'half', 'prefill' => 'absent_count'],
                    ['id' => 'days_used', 'label' => 'Days Used / Allocation', 'type' => 'text', 'width' => 'half', 'placeholder' => '/ 12 days'],
                ],
            ],
            [
                'title'  => 'Section 2 — Reason for Absence',
                'fields' => [
                    ['id' => 'nature', 'label' => 'Nature of Absence', 'type' => 'checkbox', 'width' => 'full', 'options' => ['Personal illness', 'Family emergency', 'Bereavement', 'Medical appointment', 'Other']],
                    ['id' => 'nature_other', 'label' => 'If other, please specify', 'type' => 'text', 'width' => 'full'],
                    ['id' => 'further_details', 'label' => 'Further Details', 'type' => 'textarea', 'width' => 'full'],
                    ['id' => 'supporting_doc', 'label' => 'Supporting Document', 'type' => 'checkbox', 'width' => 'full', 'options' => ['Medical certificate attached', 'Not applicable', 'Not provided']],
                    ['id' => 'supporting_reason', 'label' => 'If not provided, reason', 'type' => 'text', 'width' => 'full'],
                ],
            ],
            [
                'title'  => 'Section 3 — Absence Reporting Process',
                'fields' => [
                    ['id' => 'reporting_followed', 'label' => 'Reporting procedure followed?', 'type' => 'radio', 'width' => 'half', 'options' => ['Yes', 'No']],
                    ['id' => 'notified_on', 'label' => 'Notified Line Manager / HR on', 'type' => 'date', 'width' => 'half'],
                    ['id' => 'reason_not_reporting', 'label' => 'If No, reason for not reporting', 'type' => 'textarea', 'width' => 'full'],
                    ['id' => 'policy_note', 'label' => '', 'type' => 'note', 'width' => 'full', 'text' => 'Policy reminder: Unplanned leave entitlement is 12 days per year. Once exhausted, further unplanned absences will be treated as unpaid leave. Repeated unplanned absences will be managed through the Company’s conduct and capability process.'],
                ],
            ],
            [
                'title'  => 'Section 4 — Acknowledgement & Signatures',
                'fields' => [
                    ['id' => 'ack_note', 'label' => '', 'type' => 'note', 'width' => 'full', 'text' => 'By signing below the employee confirms they have read, understood, and agreed to the contents of this return to work discussion.'],
                    ['id' => 'employee_signature', 'label' => 'Employee', 'type' => 'signature', 'width' => 'half'],
                    ['id' => 'manager_signature', 'label' => 'Operations & People Manager', 'type' => 'signature', 'width' => 'half'],
                ],
            ],
        ];
    }
};
