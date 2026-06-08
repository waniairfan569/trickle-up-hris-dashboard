<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfileTemplate;
use App\Models\ProfileSection;
use App\Models\ProfileField;

class DynamicProfileTemplatesSeeder extends Seeder
{
    public function run()
    {
        // TEMPLATE 1: Emergency Contact
        $template1 = ProfileTemplate::updateOrCreate(
            ['slug' => 'emergency-contact'],
            [
                'name' => 'Emergency contact',
                'type' => 'dynamic',
                'is_active' => true,
                'sort_order' => 1
            ]
        );

        $section1 = ProfileSection::updateOrCreate(
            ['template_id' => $template1->id, 'slug' => 'emergency-contact-details'],
            ['name' => 'Emergency contact details', 'icon' => 'ti-urgent', 'sort_order' => 1]
        );

        $this->seedFields($section1->id, [
            ['key' => 'emergency_name', 'name' => 'Contact full name', 'type' => 'text', 'is_required' => true, 'visibility' => 'manager', 'employee_can_edit' => true],
            ['key' => 'emergency_relationship', 'name' => 'Relationship', 'type' => 'dropdown', 'options' => ['Spouse','Parent','Sibling','Friend','Other'], 'is_required' => true, 'visibility' => 'manager', 'employee_can_edit' => true],
            ['key' => 'emergency_phone', 'name' => 'Contact phone', 'type' => 'phone', 'is_required' => true, 'visibility' => 'manager', 'employee_can_edit' => true],
            ['key' => 'emergency_email', 'name' => 'Contact email', 'type' => 'email', 'is_required' => false, 'visibility' => 'manager', 'employee_can_edit' => true],
        ]);

        // TEMPLATE 2: Bank and payroll details
        $template2 = ProfileTemplate::updateOrCreate(
            ['slug' => 'bank-payroll-details'],
            [
                'name' => 'Bank & payroll details',
                'type' => 'dynamic',
                'is_active' => true,
                'sort_order' => 2
            ]
        );

        $section2 = ProfileSection::updateOrCreate(
            ['template_id' => $template2->id, 'slug' => 'bank-details'],
            ['name' => 'Bank details', 'icon' => 'ti-building-bank', 'sort_order' => 1]
        );

        $this->seedFields($section2->id, [
            ['key' => 'bank_name', 'name' => 'Bank name', 'type' => 'text', 'is_required' => true, 'visibility' => 'internal', 'employee_can_edit' => true],
            ['key' => 'account_number', 'name' => 'Account number', 'type' => 'text', 'is_required' => true, 'is_encrypted' => true, 'visibility' => 'internal', 'employee_can_edit' => true],
            ['key' => 'sort_code', 'name' => 'Sort code / IBAN', 'type' => 'text', 'is_required' => true, 'is_encrypted' => true, 'visibility' => 'internal', 'employee_can_edit' => true],
            ['key' => 'payment_method', 'name' => 'Payment method', 'type' => 'dropdown', 'options' => ['Bank transfer','Cheque','Cash'], 'is_required' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
        ]);

        // TEMPLATE 3: Engineering details
        $template3 = ProfileTemplate::updateOrCreate(
            ['slug' => 'engineering-details'],
            [
                'name' => 'Engineering details',
                'type' => 'dynamic',
                'is_active' => true,
                'sort_order' => 3
            ]
        );

        $section3 = ProfileSection::updateOrCreate(
            ['template_id' => $template3->id, 'slug' => 'technical-profile'],
            ['name' => 'Technical profile', 'icon' => 'ti-code', 'sort_order' => 1]
        );

        $this->seedFields($section3->id, [
            ['key' => 'primary_language', 'name' => 'Primary language', 'type' => 'dropdown', 'options' => ['PHP','JavaScript','Python','Java','Go','Rust','Other'], 'is_required' => false, 'visibility' => 'public', 'employee_can_edit' => true],
            ['key' => 'tech_stack', 'name' => 'Tech stack', 'type' => 'multi_select', 'options' => ['Laravel','React','Vue','Node.js','MySQL','PostgreSQL','AWS','Docker','Tailwind'], 'is_required' => false, 'visibility' => 'public', 'employee_can_edit' => true],
            ['key' => 'github_handle', 'name' => 'GitHub username', 'type' => 'text', 'is_required' => false, 'visibility' => 'public', 'employee_can_edit' => true],
            ['key' => 'laptop_serial', 'name' => 'Laptop serial number', 'type' => 'text', 'is_required' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
        ]);

        // TEMPLATE 4: Visa and right to work
        $template4 = ProfileTemplate::updateOrCreate(
            ['slug' => 'visa-right-to-work'],
            [
                'name' => 'Visa & right to work',
                'type' => 'dynamic',
                'is_active' => true,
                'sort_order' => 4
            ]
        );

        $section4 = ProfileSection::updateOrCreate(
            ['template_id' => $template4->id, 'slug' => 'right-to-work-documents'],
            ['name' => 'Right to work documents', 'icon' => 'ti-passport', 'sort_order' => 1]
        );

        $this->seedFields($section4->id, [
            ['key' => 'visa_type', 'name' => 'Visa type', 'type' => 'dropdown', 'options' => ['Tier 2 Skilled Worker','Student visa','ILR','British citizen','EU Settled Status','Other'], 'is_required' => true, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'visa_expiry_date', 'name' => 'Visa expiry date', 'type' => 'date', 'is_required' => true, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'passport_number', 'name' => 'Passport number', 'type' => 'text', 'is_required' => true, 'is_encrypted' => true, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'right_to_work_doc', 'name' => 'Right to work document', 'type' => 'file', 'is_required' => true, 'visibility' => 'internal', 'employee_can_edit' => false],
            ['key' => 'right_to_work_expiry', 'name' => 'Document expiry date', 'type' => 'date', 'is_required' => false, 'visibility' => 'internal', 'employee_can_edit' => false],
        ]);
    }

    private function seedFields($sectionId, $fields)
    {
        $sortOrder = 1;
        foreach ($fields as $fieldData) {
            // Apply defaults for optional booleans to avoid null constraint errors or mismatches
            $fieldData['is_system'] = $fieldData['is_system'] ?? false;
            $fieldData['is_encrypted'] = $fieldData['is_encrypted'] ?? false;
            
            ProfileField::updateOrCreate(
                ['key' => $fieldData['key']],
                array_merge($fieldData, [
                    'section_id' => $sectionId,
                    'sort_order' => $sortOrder++
                ])
            );
        }
    }
}
