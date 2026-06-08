<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_entity_id',
        'name',
        'description',
        'hours_per_day',
        'days_per_week',
        'working_days',
        'start_time',
        'end_time',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'working_days' => 'array',
        'hours_per_day' => 'float',
        'days_per_week' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the company entity that owns the schedule.
     */
    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    /**
     * Get the employees assigned to this schedule.
     */
    public function employees()
    {
        return $this->hasMany(User::class, 'work_schedule_id');
    }

    /**
     * Scope a query to only include default schedules.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Check if a given date is a working day according to this schedule.
     * Note: "Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun" format expected.
     */
    public function isWorkingDay(Carbon $date): bool
    {
        if (empty($this->working_days) || !is_array($this->working_days)) {
            return false;
        }

        $shortDayName = $date->format('D'); // e.g., "Mon"
        return in_array($shortDayName, $this->working_days);
    }

    /**
     * Count the number of working days between two dates.
     * Includes start and end dates.
     * Optionally excludes public holidays if a user ID is provided.
     */
    public function countWorkingDays(Carbon $from, Carbon $to, ?int $userId = null): int
    {
        $count = 0;
        
        // Clone dates so we don't modify the originals during loop
        $current = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($current->gt($end)) {
            return 0; // If from is after to, return 0
        }

        while ($current->lte($end)) {
            if ($this->isWorkingDay($current)) {
                $isHoliday = false;
                
                // If userId is provided, check if it's a public holiday for this user
                if ($userId !== null && class_exists(Holiday::class)) {
                    if (Holiday::isPublicHoliday($current, $userId)) {
                        $isHoliday = true;
                    }
                }
                
                if (!$isHoliday) {
                    $count++;
                }
            }
            $current->addDay();
        }

        return $count;
    }
}
