<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shift start (lateness base)
    |--------------------------------------------------------------------------
    | The on-time shift start, in the employee's local timezone, used as the
    | base for lateness. The grace-period minutes from the Attendance Report
    | Settings page are added on top: cutoff = shift start + grace. So with a
    | 09:30 shift and 0 grace, 09:30 onward is late; with 5 min grace, 09:35
    | onward is late. An employee's own work-schedule start_time (if set)
    | overrides this base. Change here or via ATTENDANCE_LATE_AFTER (e.g. "10:00").
    */
    'late_after' => env('ATTENDANCE_LATE_AFTER', '09:30'),
];
