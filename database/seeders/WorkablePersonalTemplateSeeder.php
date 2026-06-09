<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Aligns the DEFAULT template's "Personal" area with Workable's Personal tab:
 * adds Marital status / Birthplace / Clothing size / SSN / Team to the existing
 * Personal section, and creates "Contact details" and "Medical Records" sections.
 *
 * Idempotent — safe to run repeatedly (firstOrCreate by section slug / field key).
 */
class WorkablePersonalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = ProfileTemplate::where('type', 'default')->first();
        if (!$template) {
            $this->command?->warn('No default profile template found — skipping.');
            return;
        }

        // --- 1. Add fields to the existing "Personal information" section ---
        $personal = ProfileSection::where('template_id', $template->id)
            ->where('slug', 'personal-info')->first()
            ?? ProfileSection::where('template_id', $template->id)->orderBy('sort_order')->first();

        if ($personal) {
            $this->addFields($personal, [
                ['preferred_name',        'Preferred name',        'text',     'internal', true],
                ['marital_status',        'Marital status',        'dropdown', 'private',  true,  ['Single', 'Married', 'Divorced', 'Widowed']],
                ['marital_certificate',   'Marital certificate',   'text',     'private',  false],
                ['birthplace',            'Birthplace',            'text',     'internal', true],
                ['clothing_size',         'Clothing size',         'dropdown', 'internal', true,  ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
                ['social_security_number','Social Security number','text',     'private',  false],
                ['team',                  'Team',                  'text',     'public',   false],
            ]);
        }

        // --- 2. Contact details section ---
        $contact = ProfileSection::firstOrCreate(
            ['template_id' => $template->id, 'slug' => 'contact-details'],
            ['name' => 'Contact details', 'icon' => 'ti-id-badge', 'sort_order' => 5]
        );
        $this->addFields($contact, [
            ['phone_type',        'Phone type',       'dropdown', 'internal', true, ['Mobile', 'Work', 'Home']],
            ['phone_extension',   'Phone extension',  'text',     'internal', true],
            ['chat_type',         'Chat / video app', 'dropdown', 'public',   true, ['Slack', 'Microsoft Teams', 'Zoom', 'Skype', 'Google Meet']],
            ['chat_username',     'Chat username',    'text',     'public',   true],
            ['social_media_type', 'Social media',     'dropdown', 'public',   true, ['LinkedIn', 'Twitter/X', 'GitHub', 'Facebook', 'Instagram']],
            ['social_media_url',  'Social media URL', 'url',      'public',   true],
        ]);

        // --- 3. Medical Records section ---
        $medical = ProfileSection::firstOrCreate(
            ['template_id' => $template->id, 'slug' => 'medical-records'],
            ['name' => 'Medical Records', 'icon' => 'ti-urgent', 'sort_order' => 6]
        );
        $this->addFields($medical, [
            ['medical_conditions',  'Medical conditions & allergies', 'textarea', 'private', true],
            ['blood_group',         'Blood group',                    'dropdown', 'private', true, ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']],
            ['dietary_restrictions','Dietary restrictions',           'text',     'private', true],
        ]);

        $this->command?->info('Workable Personal template fields synced.');
    }

    /**
     * @param array<int, array{0:string,1:string,2:string,3:string,4:bool,5?:array}> $fields
     */
    private function addFields(ProfileSection $section, array $fields): void
    {
        $sort = ProfileField::where('section_id', $section->id)->max('sort_order') ?? 0;

        foreach ($fields as $def) {
            [$key, $name, $type, $visibility, $canEdit] = $def;
            $options = $def[5] ?? null;

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
                    'sort_order'        => ++$sort,
                ]
            );
        }
    }
}
