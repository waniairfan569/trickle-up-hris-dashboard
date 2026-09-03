<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** Per-workspace config for the daily admin reminders. Singleton per tenant. */
class AdminReminderSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'wfh_enabled', 'wfh_send_time', 'wfh_last_sent_on',
        'late_enabled', 'late_send_time', 'late_last_sent_on',
        'timezone',
    ];

    protected $casts = [
        'wfh_enabled' => 'boolean',
        'late_enabled' => 'boolean',
        'wfh_last_sent_on' => 'date',
        'late_last_sent_on' => 'date',
    ];

    public static function getSettings(): self
    {
        return static::firstOrCreate([], [
            'wfh_enabled' => false,
            'wfh_send_time' => '08:00:00',
            'late_enabled' => false,
            'late_send_time' => '10:00:00',
            'timezone' => config('app.timezone') && config('app.timezone') !== 'UTC'
                ? config('app.timezone')
                : 'Europe/London',
        ]);
    }

    public function effectiveTimezone(): string
    {
        return $this->timezone ?: 'Europe/London';
    }

    /** "HH:MM" of a time column in this workspace's timezone. */
    public function timeLabel(string $column): string
    {
        return Carbon::parse((string) $this->{$column})->format('H:i');
    }
}
