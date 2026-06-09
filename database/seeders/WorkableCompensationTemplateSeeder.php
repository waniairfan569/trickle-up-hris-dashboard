<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use Illuminate\Database\Seeder;

/**
 * Adds Workable's "Compensation & benefits" → Bank account → Bank details section
 * to the DEFAULT template. All fields are PRIVATE (HR/self only). Idempotent.
 */
class WorkableCompensationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = ProfileTemplate::where('type', 'default')->first();
        if (!$template) {
            $this->command?->warn('No default profile template found — skipping.');
            return;
        }

        $bank = ProfileSection::firstOrCreate(
            ['template_id' => $template->id, 'slug' => 'bank-details'],
            ['name' => 'Bank details', 'icon' => 'ti-building-bank', 'sort_order' => 9]
        );

        $this->addFields($bank, [
            ['bank_name',          'Bank name',                 'text', 'private', true],
            ['iban',               'IBAN',                      'text', 'private', true],
            ['account_number',     'Account number',            'text', 'private', true],
            ['bic_swift',          'BIC (SWIFT)',               'text', 'private', true],
            ['aba_transit_number', 'ABA / Transit number',      'text', 'private', true],
            ['bank_state_branch',  'Bank State Branch (BSB)',   'text', 'private', true],
            ['routing_number',     'Routing number',            'text', 'private', true],
            ['sort_code',          'Sort code',                 'text', 'private', true],
        ]);

        $this->command?->info('Workable Compensation (bank details) fields synced.');
    }

    private function addFields(ProfileSection $section, array $fields): void
    {
        $sort = ProfileField::where('section_id', $section->id)->max('sort_order') ?? 0;

        foreach ($fields as [$key, $name, $type, $visibility, $canEdit]) {
            ProfileField::firstOrCreate(
                ['key' => $key],
                [
                    'section_id'        => $section->id,
                    'name'              => $name,
                    'type'              => $type,
                    'options'           => null,
                    'placeholder'       => null,
                    'is_required'       => false,
                    'is_system'         => false,
                    'is_encrypted'      => false,
                    'visibility'        => $visibility,
                    'employee_can_edit' => $canEdit,
                    'sort_order'        => ++$sort,
                ]
            );
        }
    }
}
