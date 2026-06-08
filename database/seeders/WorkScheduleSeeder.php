<?php

namespace Database\Seeders;

use App\Models\CompanyEntity;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class WorkScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entity = CompanyEntity::primary() ?? CompanyEntity::first();
        $entityId = $entity ? $entity->id : null;

        WorkSchedule::query()->forceDelete();

        // 1. Standard Office
        WorkSchedule::create([
            'company_entity_id' => $entityId,
            'name' => 'Standard Office',
            'description' => 'Standard Mon-Fri 9-5 schedule',
            'hours_per_day' => 8.0,
            'days_per_week' => 5,
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_default' => true,
            'is_active' => true,
        ]);

        // 2. Remote Flexible
        WorkSchedule::create([
            'company_entity_id' => $entityId,
            'name' => 'Remote Flexible',
            'description' => 'Flexible hours across 5 days',
            'hours_per_day' => 8.0,
            'days_per_week' => 5,
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'is_default' => false,
            'is_active' => true,
        ]);
    }
}
