<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use Illuminate\Database\Seeder;

/**
 * Adds Workable's "Legal documents" tab to the DEFAULT template:
 * ID documents (CNIC, Social insurance, Tax ID), Citizenship, Passport, Work visa.
 * All fields are PRIVATE. Idempotent — existing keys are MOVED into the right
 * default section (preserving their type + any stored values).
 */
class WorkableLegalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = ProfileTemplate::where('type', 'default')->first();
        if (!$template) {
            $this->command?->warn('No default profile template found — skipping.');
            return;
        }
        $this->tplId = $template->id;

        $cnic = $this->section('id-cnic', 'National ID (CNIC)', 'ti-passport', 10);
        $this->fields($cnic, [
            ['cnic_number',     'Number',     'text', 1],
            ['cnic_issue_date', 'Issue date', 'date', 2],
            ['cnic_file',       'File',       'file', 3],
        ]);

        $sin = $this->section('social-insurance', 'Social insurance number', 'ti-id-badge', 11);
        $this->fields($sin, [
            ['sin_number',            'Number',            'text', 1],
            ['sin_issue_date',        'Issue date',        'date', 2],
            ['sin_insurance_carrier', 'Insurance carrier', 'text', 3],
            ['sin_file',              'File',              'file', 4],
        ]);

        $tax = $this->section('tax-id', 'Tax identification number', 'ti-id-badge', 12);
        $this->fields($tax, [
            ['tax_number',     'Number',     'text', 1],
            ['tax_issue_date', 'Issue date', 'date', 2],
            ['tax_file',       'File',       'file', 3],
        ]);

        $citizenship = $this->section('citizenship', 'Citizenship', 'ti-passport', 13);
        $this->fields($citizenship, [
            ['nationality', 'Nationality', 'dropdown', 1], // moved out of Personal info
            ['citizenship', 'Citizenship', 'text',     2],
        ]);

        $passport = $this->section('passport', 'Passport', 'ti-passport', 14);
        $this->fields($passport, [
            ['passport_country',     'Country',     'text', 1],
            ['passport_number',      'Number',      'text', 2], // moved from dynamic template
            ['passport_issue_date',  'Issue date',  'date', 3],
            ['passport_expiry_date', 'Expiry date', 'date', 4],
            ['passport_file',        'File',        'file', 5],
        ]);

        $visa = $this->section('work-visa', 'Work visa', 'ti-passport', 15);
        $this->fields($visa, [
            ['visa_country',     'Country',     'text', 1],
            ['visa_type',        'Type',        'text', 2], // moved
            ['visa_number',      'Number',      'text', 3],
            ['visa_issue_date',  'Issue date',  'date', 4],
            ['visa_expiry_date', 'Expiry date', 'date', 5], // moved
            ['visa_file',        'File',        'file', 6],
        ]);

        $this->command?->info('Workable Legal documents template fields synced.');
    }

    private int $tplId;

    private function section(string $slug, string $name, string $icon, int $sort): ProfileSection
    {
        return ProfileSection::firstOrCreate(
            ['template_id' => $this->tplId, 'slug' => $slug],
            ['name' => $name, 'icon' => $icon, 'sort_order' => $sort]
        );
    }

    /**
     * Move-or-create each field into the section. Existing fields keep their
     * type/options (and stored values); only grouping + visibility are refreshed.
     *
     * @param array<int, array{0:string,1:string,2:string,3:int}> $defs
     */
    private function fields(ProfileSection $section, array $defs): void
    {
        foreach ($defs as [$key, $name, $type, $sort]) {
            $field = ProfileField::where('key', $key)->first();

            if ($field) {
                $field->section_id        = $section->id;
                $field->visibility        = 'private';
                $field->employee_can_edit = true;
                $field->sort_order        = $sort;
                $field->save();
                continue;
            }

            ProfileField::create([
                'section_id'        => $section->id,
                'name'              => $name,
                'key'               => $key,
                'type'              => $type,
                'options'           => null,
                'placeholder'       => null,
                'is_required'       => false,
                'is_system'         => false,
                'is_encrypted'      => false,
                'visibility'        => 'private',
                'employee_can_edit' => true,
                'sort_order'        => $sort,
            ]);
        }
    }
}
