<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            DepartmentSeeder::class,
            LocationSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            PublicHolidaySeeder::class,
            DefaultProfileTemplateSeeder::class,
            DynamicProfileTemplatesSeeder::class,

            // Workable-style profile fields (must run after the base templates exist).
            WorkablePersonalTemplateSeeder::class,
            WorkableJobTemplateSeeder::class,
            WorkableCompensationTemplateSeeder::class,
            WorkableLegalTemplateSeeder::class,
            WorkableExperienceTemplateSeeder::class,
            WorkableEmergencyTemplateSeeder::class,
            // Assign every section to its profile tab (run last — after all sections exist).
            ProfileSectionTabSeeder::class,

            // Document library categories.
            DocumentCategorySeeder::class,
        ]);
    }
}
