<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Late arrival cutoff
    |--------------------------------------------------------------------------
    | An employee whose clock-in (in their own local timezone) is AFTER this
    | time of day is marked "late". Default 09:30. Change here or via the
    | ATTENDANCE_LATE_AFTER env value (e.g. "10:00").
    */
    'late_after' => env('ATTENDANCE_LATE_AFTER', '09:30'),
];
