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
        ]);
    }
}
