<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use Illuminate\Database\Seeder;

/**
 * Aligns the DEFAULT template's "Job" area with Workable's Job tab:
 * adds Hire date + Notice period to Work information, and creates
 * "Probation" and "Contract details" sections.
 *
 * Idempotent — firstOrCreate by section slug / field key.
 */
class WorkableJobTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = ProfileTemplate::where('type', 'default')->first();
        if (!$template) {
            $this->command?->warn('No default profile template found — skipping.');
            return;
        }

        // --- Work information (Basic): add the few missing native-backed fields ---
        $work = ProfileSection::where('template_id', $template->id)->where('slug', 'work-info')->first();
        if ($work) {
            $this->addFields($work, [
                ['hire_date',          'Hire date',           'date',   'public',   true],
                ['notice_period_days', 'Notice period (days)','number', 'internal', false],
            ]);
        }

        // --- Probation section ---
        $probation = ProfileSection::firstOrCreate(
            ['template_id' => $template->id, 'slug' => 'probation'],
            ['name' => 'Probation', 'icon' => 'ti-id-badge', 'sort_order' => 7]
        );
        // Move the existing probation_end_date into the Probation group (date field — no options to lose).
        ProfileField::where('key', 'probation_end_date')->update(['section_id' => $probation->id, 'sort_order' => 2]);
        $this->addFields($probation, [
            ['probation_start_date', 'Start date', 'date',     'manager', false, null, 1],
            ['probation_note',       'Note',       'textarea', 'manager', false, null, 3],
        ]);

        // --- Contract details section (Employment group) ---
        $contract = ProfileSection::firstOrCreate(
            ['template_id' => $template->id, 'slug' => 'contract-details'],
            ['name' => 'Contract details', 'icon' => 'ti-briefcase', 'sort_order' => 8]
        );
        $this->addFields($contract, [
            ['contract_effective_date', 'Effective date',    'date',     'internal', false],
            ['workplace',               'Workplace',         'dropdown', 'public',   true,  ['On-site', 'Remote', 'Hybrid']],
            ['contract_expiry_date',    'Expiry date',       'date',     'internal', false],
            ['contract_note',           'Note',              'textarea', 'internal', false],
            ['work_schedule',           'Work schedule',     'text',     'public',   false],
            ['employment_status',       'Employment status', 'dropdown', 'public',   false, ['Permanent', 'Contract', 'Temporary', 'Internship', 'Probationary']],
        ]);

        $this->command?->info('Workable Job template fields synced.');
    }

    /**
     * @param array<int, array{0:string,1:string,2:string,3:string,4:bool,5?:?array,6?:int}> $fields
     */
    private function addFields(ProfileSection $section, array $fields): void
    {
        $auto = ProfileField::where('section_id', $section->id)->max('sort_order') ?? 0;

        foreach ($fields as $def) {
            [$key, $name, $type, $visibility, $canEdit] = $def;
            $options = $def[5] ?? null;
            $sort = $def[6] ?? ++$auto;

            ProfileField::firstOrCreate(
                ['key' => $key],
                [
                    'section_id'        => $section->id,
                    'name'              => $name,
                    'type'              => $type,
                    'options'           => $options,
                    'placeholder'       => null,
                    'is_required'       => false,
                    'is_system'         => false,
                    'is_encrypted'      => false,
                    'visibility'        => $visibility,
                    'employee_can_edit' => $canEdit,
                    'sort_order'        => $sort,
                ]
            );
        }
    }
}
