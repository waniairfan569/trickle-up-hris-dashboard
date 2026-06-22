<?php

namespace Database\Seeders;

use App\Models\CompanyForm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ITEquipmentRequirementFormSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['super_admin', 'hr_admin']))->first();

        $form = CompanyForm::firstOrCreate(
            ['slug' => 'it-equipment-requirement-form'],
            [
                'title' => 'IT Equipment Requirement Form',
                'description' => 'Please fill in all required fields to submit your IT equipment request.',
                'status' => 'active',
                'is_anonymous' => false,
                'allow_multiple_submissions' => true,
                'show_progress_bar' => false,
                'requires_signature' => false,
                'created_by' => $admin?->id,
            ]
        );

        if ($form->fields()->exists()) {
            return;
        }

        $fields = [
            ['type' => 'date', 'label' => 'Date', 'is_required' => true],
            ['type' => 'text', 'label' => 'Employee Name', 'is_required' => true],
            ['type' => 'text', 'label' => 'Department'],
            ['type' => 'text', 'label' => 'Designation'],
            ['type' => 'text', 'label' => 'Equipment Required'],
            ['type' => 'number', 'label' => 'Quantity'],
            ['type' => 'textarea', 'label' => 'Reason/Justification'],
            ['type' => 'date', 'label' => 'Required By Date'],
            ['type' => 'dropdown', 'label' => 'Manager Approval (Y/N)', 'options' => ['Yes', 'No']],
            ['type' => 'text', 'label' => 'Manager Name'],
        ];

        $used = [];
        foreach ($fields as $i => $f) {
            $base = substr(Str::slug($f['label'], '_') ?: 'field', 0, 50);
            $key = $base;
            $n = 1;
            while (in_array($key, $used, true)) {
                $key = $base . '_' . (++$n);
            }
            $used[] = $key;

            $form->fields()->create([
                'label' => $f['label'],
                'field_key' => $key,
                'type' => $f['type'],
                'help_text' => $f['help_text'] ?? null,
                'options' => $f['options'] ?? null,
                'is_required' => $f['is_required'] ?? false,
                'width' => 'full',
                'sort_order' => $i + 1,
            ]);
        }
    }
}
