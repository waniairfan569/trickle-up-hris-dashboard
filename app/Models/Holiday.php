<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'name',
        'date',
        'is_recurring',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function calendar()
    {
        return $this->belongsTo(HolidayCalendar::class, 'calendar_id');
    }

    /**
     * Check if a given date is a public holiday for a specific user.
     * Takes into account the user's assigned calendars and recurring holidays.
     */
    public static function isPublicHoliday(Carbon $date, int $userId): bool
    {
        // Get all calendar IDs assigned to this user
        $calendarIds = DB::table('calendar_user')->where('user_id', $userId)->pluck('calendar_id');

        if ($calendarIds->isEmpty()) {
            return false;
        }

        $dateString = $date->format('Y-m-d');
        $monthDayString = $date->format('m-d'); // For recurring holidays

        // Check if there is any holiday on this date (or recurring on this month/day)
        // in any of the calendars assigned to the user
        return self::whereIn('calendar_id', $calendarIds)
            ->where(function ($query) use ($dateString, $monthDayString) {
                $query->where('date', $dateString)
                      ->orWhere(function ($q) use ($monthDayString) {
                          $q->where('is_recurring', true)
                            // Comparing substring for month-day in SQLite/MySQL
                            // A portable way for simple formats is LIKE %-mm-dd
                            ->where('date', 'like', '%-' . $monthDayString);
                      });
            })
            ->exists();
    }
}
