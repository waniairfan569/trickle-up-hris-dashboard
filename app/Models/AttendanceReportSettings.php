<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AttendanceReportSettings extends Model
{
    protected $fillable = [
        'company_entity_id', 'is_enabled', 'send_time', 'working_days',
        'send_to_super_admin', 'send_to_hr_admin', 'send_to_managers', 'additional_emails',
        'include_late', 'include_absent', 'include_early_departure', 'include_overtime',
        'include_on_leave', 'include_full_table', 'attach_excel', 'late_threshold_minutes',
        'report_subject_template',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'send_to_super_admin' => 'boolean',
        'send_to_hr_admin' => 'boolean',
        'send_to_managers' => 'boolean',
        'include_late' => 'boolean',
        'include_absent' => 'boolean',
        'include_early_departure' => 'boolean',
        'include_overtime' => 'boolean',
        'include_on_leave' => 'boolean',
        'include_full_table' => 'boolean',
        'attach_excel' => 'boolean',
        'working_days' => 'array',
        'additional_emails' => 'array',
        'late_threshold_minutes' => 'integer',
    ];

    /** The single settings row (created with defaults on first use). */
    public static function getSettings(): self
    {
        return static::firstOrCreate([], [
            'is_enabled' => true,
            'send_time' => '22:00:00',
            'working_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
            'additional_emails' => [],
            'report_subject_template' => 'Daily Attendance Report — {{date}}',
        ]);
    }

    public function isWorkingDay(Carbon $date): bool
    {
        $days = $this->working_days ?: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

        return in_array($date->format('D'), $days, true);
    }

    public function getSendTimeCarbon(Carbon $date): Carbon
    {
        return Carbon::parse($date->toDateString() . ' ' . ($this->send_time ?: '22:00:00'));
    }
}
