<?php

namespace Database\Seeders;

use App\Models\AttendanceReportSettings;
use Illuminate\Database\Seeder;

class AttendanceReportSettingsSeeder extends Seeder
{
    public function run(): void
    {
        AttendanceReportSettings::firstOrCreate([], [
            'is_enabled' => true,
            'send_time' => '22:00:00',
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'send_to_super_admin' => true,
            'send_to_hr_admin' => true,
            'send_to_managers' => true,
            'additional_emails' => [],
            'include_late' => true,
            'include_absent' => true,
            'include_early_departure' => true,
            'include_overtime' => true,
            'include_on_leave' => true,
            'include_full_table' => true,
            'attach_excel' => false,
            'late_threshold_minutes' => 15,
            'report_subject_template' => 'Daily Attendance Report — {{date}}',
        ]);
    }
}
