<?php

namespace Database\Seeders;

use App\Models\ProfileSection;
use Illuminate\Database\Seeder;

/**
 * Assigns each profile section to a Workable-style profile tab.
 * Idempotent — safe to run after the Workable*TemplateSeeders.
 */
class ProfileSectionTabSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'personal'     => ['personal-info', 'contact-details', 'medical-records'],
            'job'          => ['work-info', 'probation', 'contract-details', 'employment-status'],
            'compensation' => ['compensation', 'bank-details'],
            'legal'        => ['id-cnic', 'social-insurance', 'tax-id', 'citizenship', 'passport', 'work-visa'],
            'experience'   => ['education', 'work-experience', 'skills', 'languages', 'resume'],
            'emergency'    => ['emergency-contact'],
        ];

        foreach ($map as $tab => $slugs) {
            ProfileSection::whereIn('slug', $slugs)->update(['tab' => $tab]);
        }

        $this->command?->info('Profile section tabs assigned.');
    }
}
