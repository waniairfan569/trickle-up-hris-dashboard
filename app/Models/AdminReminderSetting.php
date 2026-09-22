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
        'overtime_enabled', 'overtime_frequency', 'overtime_day', 'overtime_weekday',
        'overtime_send_time', 'overtime_recipients', 'overtime_last_sent_key',
    ];

    protected $casts = [
        'wfh_enabled' => 'boolean',
        'late_enabled' => 'boolean',
        'wfh_last_sent_on' => 'date',
        'late_last_sent_on' => 'date',
        'overtime_enabled' => 'boolean',
        'overtime_recipients' => 'array',
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

    // ── Overtime report reminder ────────────────────────────────────────────

    /** Per-period guard key so the overtime reminder fires once per cadence. */
    public function overtimeSendKey(?Carbon $now = null): string
    {
        $now = $now ?: now($this->effectiveTimezone());

        return ($this->overtime_frequency ?? 'monthly') === 'weekly'
            ? $now->format('o-\WW')   // ISO year + week
            : $now->format('Y-m');    // calendar month
    }

    /**
     * Is the overtime reminder due right now (enabled, on its scheduled day, at or
     * past its send time, and not already sent this period)? Uses >= on the time
     * so a skipped scheduler minute still fires.
     */
    public function overtimeDueNow(?Carbon $now = null): bool
    {
        if (! $this->overtime_enabled) {
            return false;
        }

        $now = $now ?: now($this->effectiveTimezone());
        $sendHm = Carbon::parse((string) ($this->overtime_send_time ?: '09:00'))->format('H:i');

        if ($now->format('H:i') < $sendHm) {
            return false;
        }
        if ($this->overtime_last_sent_key === $this->overtimeSendKey($now)) {
            return false;
        }

        if (($this->overtime_frequency ?? 'monthly') === 'weekly') {
            return $now->dayOfWeekIso === (int) ($this->overtime_weekday ?: 1);
        }

        // Monthly — clamp the chosen day to the month's length (e.g. 31 → 28/30).
        $day = min((int) ($this->overtime_day ?: 1), $now->daysInMonth);

        return $now->day === $day;
    }

    /** Human summary of the overtime reminder schedule. */
    public function overtimeScheduleLabel(): string
    {
        $time = Carbon::parse((string) ($this->overtime_send_time ?: '09:00'))->format('H:i');

        if (($this->overtime_frequency ?? 'monthly') === 'weekly') {
            $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
            return 'Every ' . ($days[(int) ($this->overtime_weekday ?: 1)] ?? 'Monday') . ' at ' . $time;
        }

        $day = (int) ($this->overtime_day ?: 1);
        $suffix = in_array($day % 100, [11, 12, 13]) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$day % 10] ?? 'th');

        return "Monthly on the {$day}{$suffix} at {$time}";
    }
}
