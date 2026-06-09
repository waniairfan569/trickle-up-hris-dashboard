<?php

namespace Database\Seeders;

use App\Models\ProfileField;
use App\Models\ProfileSection;
use App\Models\ProfileTemplate;
use Illuminate\Database\Seeder;

/**
 * Adds Workable's "Experience" tab to the DEFAULT template:
 * Education, Work experience, Skills, Languages, Resume.
 * Non-sensitive (visible to colleagues), employee-editable. Idempotent
 * (existing keys are moved into the right section, preserving type/values).
 */
class WorkableExperienceTemplateSeeder extends Seeder
{
    private int $tplId;

    public function run(): void
    {
        $template = ProfileTemplate::where('type', 'default')->first();
        if (!$template) {
            $this->command?->warn('No default profile template found — skipping.');
            return;
        }
        $this->tplId = $template->id;

        $edu = $this->section('education', 'Education', 'ti-id-badge', 16);
        $this->fields($edu, 'public', [
            ['edu_start_date',     'Start date',     'date',     1],
            ['edu_end_date',       'End date',       'date',     2],
            ['edu_degree',         'Degree',         'text',     3],
            ['edu_field_of_study', 'Field of study', 'text',     4],
            ['edu_school',         'School',         'text',     5],
        ]);

        $work = $this->section('work-experience', 'Work experience', 'ti-briefcase', 17);
        $this->fields($work, 'public', [
            ['exp_start_date', 'Start date', 'date',     1],
            ['exp_end_date',   'End date',   'date',     2],
            ['exp_job_title',  'Job title',  'text',     3],
            ['exp_company',    'Company',    'text',     4],
            ['exp_summary',    'Summary',    'textarea', 5],
            ['exp_present',    'Present (current job)', 'checkbox', 6],
        ]);

        $skills = $this->section('skills', 'Skills', 'ti-code', 18);
        $this->fields($skills, 'public', [
            ['skill', 'Skill', 'text', 1],
        ]);

        $langs = $this->section('languages', 'Languages', 'ti-user', 19);
        $this->fields($langs, 'public', [
            ['language', 'Language', 'text', 1],
        ]);

        $resume = $this->section('resume', 'Resume', 'ti-passport', 20);
        $this->fields($resume, 'private', [
            ['resume_file', 'File', 'file', 1],
        ]);

        $this->command?->info('Workable Experience template fields synced.');
    }

    private function section(string $slug, string $name, string $icon, int $sort): ProfileSection
    {
        return ProfileSection::firstOrCreate(
            ['template_id' => $this->tplId, 'slug' => $slug],
            ['name' => $name, 'icon' => $icon, 'sort_order' => $sort]
        );
    }

    /**
     * @param array<int, array{0:string,1:string,2:string,3:int}> $defs
     */
    private function fields(ProfileSection $section, string $visibility, array $defs): void
    {
        foreach ($defs as [$key, $name, $type, $sort]) {
            $field = ProfileField::where('key', $key)->first();

            if ($field) {
                $field->section_id        = $section->id;
                $field->visibility        = $visibility;
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
                'visibility'        => $visibility,
                'employee_can_edit' => true,
                'sort_order'        => $sort,
            ]);
        }
    }
}
