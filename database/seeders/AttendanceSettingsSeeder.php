<?php

namespace Database\Seeders;

use App\Models\AttendanceSetting;
use Illuminate\Database\Seeder;

class AttendanceSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AttendanceSetting::firstOrCreate(
            [], // if empty, insert these:
            [
                'grace_period_minutes' => 15,
                'overtime_threshold_minutes' => 30,
                'early_departure_threshold_minutes' => 15,
                'allow_break_tracking' => true,
                'allow_gps_capture' => false,
                'allow_manual_entry' => true,
                'max_break_duration_minutes' => 60,
            ]
        );
    }
}
