<?php

namespace Database\Seeders;

use App\Models\CompanyEntity;
use App\Models\HolidayCalendar;
use App\Models\User;
use Illuminate\Database\Seeder;

class HolidayCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $entity = CompanyEntity::primary() ?? CompanyEntity::first();
        $entityId = $entity ? $entity->id : null;

        HolidayCalendar::query()->forceDelete();

        $calendar = HolidayCalendar::create([
            'company_entity_id' => $entityId,
            'name' => 'UK Public Holidays 2025',
            'country_code' => 'GB',
            'year' => 2025,
            'is_active' => true,
        ]);

        $holidays = [
            ['name' => 'New Year\'s Day', 'date' => '2025-01-01', 'is_recurring' => true],
            ['name' => 'Good Friday', 'date' => '2025-04-18', 'is_recurring' => false],
            ['name' => 'Easter Monday', 'date' => '2025-04-21', 'is_recurring' => false],
            ['name' => 'Early May Bank Holiday', 'date' => '2025-05-05', 'is_recurring' => false],
            ['name' => 'Spring Bank Holiday', 'date' => '2025-05-26', 'is_recurring' => false],
            ['name' => 'Summer Bank Holiday', 'date' => '2025-08-25', 'is_recurring' => false],
            ['name' => 'Christmas Day', 'date' => '2025-12-25', 'is_recurring' => true],
            ['name' => 'Boxing Day', 'date' => '2025-12-26', 'is_recurring' => true],
        ];

        foreach ($holidays as $holiday) {
            $calendar->holidays()->create($holiday);
        }

        // Assign to all users
        $userIds = User::pluck('id')->toArray();
        $syncData = [];
        foreach ($userIds as $userId) {
            $syncData[$userId] = ['assigned_by' => 1];
        }
        $calendar->users()->sync($syncData);
    }
}
