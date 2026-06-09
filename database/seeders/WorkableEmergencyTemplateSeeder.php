<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use Illuminate\Database\Seeder;

/**
 * Adds Workable's "Emergency" tab to the DEFAULT template: an emergency
 * Contact details block. Private (self/HR), employee-editable. Idempotent.
 */
class WorkableEmergencyTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = ProfileTemplate::where('type', 'default')->first();
        if (!$template) {
            $this->command?->warn('No default profile template found — skipping.');
            return;
        }

        $section = ProfileSection::firstOrCreate(
            ['template_id' => $template->id, 'slug' => 'emergency-contact'],
            ['name' => 'Emergency contact', 'icon' => 'ti-urgent', 'sort_order' => 21]
        );

        $defs = [
            ['emergency_name',         'Name',         'text',     1],
            ['emergency_relationship', 'Relationship', 'text',     2],
            ['emergency_phone',        'Phone',        'phone',    3],
            ['emergency_email',        'Email',        'email',    4],
            ['emergency_country',      'Country',      'text',     5],
            ['emergency_address',      'Address',      'textarea', 6],
        ];

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

        $this->command?->info('Workable Emergency template fields synced.');
    }
}
