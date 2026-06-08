<?php

namespace Database\Seeders;

use App\Models\CompanyEntity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CompanyEntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanyEntity::create([
            'name' => 'TechNova Ltd',
            'slug' => Str::slug('TechNova Ltd') . '-' . uniqid(),
            'legal_name' => 'TechNova UK Limited',
            'country' => 'GB',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'fiscal_year_start' => '04-01',
            'work_week_start' => 'monday',
            'working_days' => ["Mon", "Tue", "Wed", "Thu", "Fri"],
            'is_primary' => true,
            'is_active' => true,
        ]);
    }
}
