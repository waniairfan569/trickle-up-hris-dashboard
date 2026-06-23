<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\BreakRecord;
use App\Models\HolidayCalendar;
use App\Models\TimeOffRequest;
use App\Models\User;
use App\Exceptions\GeofenceException;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected $geofenceService;

    public function __construct(GeofenceService $geofenceService)
    {
        $this->geofenceService = $geofenceService;
    }

    public function clockIn(User $user, array $options = []): AttendanceRecord
    {
        $today = Carbon::today();
        
        $record = AttendanceRecord::firstOrNew([
            'user_id' => $user->id,
            'date' => $today,
        ]);

        $isReclocking = false;
        if ($record->exists && $record->clock_in) {
            if ($record->clock_out) {
                // User is clocked out, but wants to clock in again.
                $isReclocking = true;
            } else {
                throw new Exception('Already clocked in');
            }
        }

        // --- Location (best-effort, non-blocking) ---
        // Record coordinates / nearest office when available, but never block the
        // clock-in over location (missing permission, outside radius, no office, etc.).
        $lat = isset($options['lat']) ? (float) $options['lat'] : 0.0;
        $lng = isset($options['lng']) ? (float) $options['lng'] : 0.0;

        if ($lat !== 0.0 && $lng !== 0.0) {
            try {
                $geofenceCheck = $this->geofenceService->isWithinAnyOffice($user, $lat, $lng);
                $record->office_location_id = $geofenceCheck['location']->id ?? null;
                if (isset($geofenceCheck['distance']) && ($geofenceCheck['reason'] ?? null) !== 'remote_allowed') {
                    $record->distance_at_clock_in = $geofenceCheck['distance'];
                }
            } catch (\Throwable $e) {
                // No office assigned / calc error — location is optional, carry on.
            }
        }

        if ($isReclocking) {
            // Calculate the duration they were "clocked out" and log it as a break
            $breakStart = $record->clock_out;
            $breakEnd = Carbon::now();
            $duration = $breakStart->diffInMinutes($breakEnd);
            
            BreakRecord::create([
                'attendance_record_id' => $record->id,
                'break_start' => $breakStart,
                'break_end' => $breakEnd,
                'duration_minutes' => $duration,
                'break_type' => 'other',
            ]);

            $record->clock_out = null;
            $record->clock_out_ip = null;
            $record->clock_out_lat = null;
            $record->clock_out_lng = null;
            $record->status = 'present';
        } else {
            $record->clock_in = Carbon::now();
            $record->clock_in_ip = $options['ip'] ?? null;
            $record->clock_in_lat = $lat !== 0.0 ? $lat : null;
            $record->clock_in_lng = $lng !== 0.0 ? $lng : null;
            
            $record->status = 'present';
        }

        $settings = AttendanceSetting::first() ?? new AttendanceSetting();
        $workSchedule = method_exists($user, 'workSchedule') ? $user->workSchedule : null;
        
        if (!$isReclocking && $workSchedule && $workSchedule->start_time) {
            $expectedStart = Carbon::parse($today->format('Y-m-d') . ' ' . $workSchedule->start_time);
            $graceEnd = $expectedStart->copy()->addMinutes($settings->grace_period_minutes);
            
            if (now()->greaterThan($graceEnd)) {
                $record->late_minutes = $expectedStart->diffInMinutes(now());
                $record->status = 'late';
            }
        }

        $record->save();
        return $record;
    }

    public function clockOut(User $user, array $options = []): AttendanceRecord
    {
        $today = Carbon::today();
        
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $today)->first();

        if (!$record || !$record->clock_in) {
            throw new Exception('Not clocked in');
        }

        if ($record->clock_out) {
            throw new Exception('Already clocked out');
        }

        // --- Location (best-effort, non-blocking) ---
        $lat = isset($options['lat']) ? (float) $options['lat'] : 0.0;
        $lng = isset($options['lng']) ? (float) $options['lng'] : 0.0;

        if ($lat !== 0.0 && $lng !== 0.0) {
            try {
                $geofenceCheck = $this->geofenceService->isWithinAnyOffice($user, $lat, $lng);
                if (isset($geofenceCheck['distance']) && ($geofenceCheck['reason'] ?? null) !== 'remote_allowed') {
                    $record->distance_at_clock_out = $geofenceCheck['distance'];
                }
            } catch (\Throwable $e) {
                // location optional — carry on.
            }
        }

        $record->clock_out = now();
        $record->clock_out_ip = $options['ip'] ?? null;
        $record->clock_out_lat = $options['lat'] ?? null;
        $record->clock_out_lng = $options['lng'] ?? null;

        // Auto close any open breaks
        $openBreak = BreakRecord::where('attendance_record_id', $record->id)->whereNull('break_end')->first();
        if ($openBreak) {
            $openBreak->update(['break_end' => now()]);
        }

        // Calculate break minutes
        $total_break_minutes = BreakRecord::where('attendance_record_id', $record->id)
            ->whereNotNull('break_end')
            ->sum('duration_minutes');
            
        $worked = $record->clock_in->diffInMinutes($record->clock_out) - $total_break_minutes;
        $record->total_minutes_worked = max(0, $worked);

        $settings = AttendanceSetting::first() ?? new AttendanceSetting();
        $workSchedule = method_exists($user, 'workSchedule') ? $user->workSchedule : null;

        if ($workSchedule && $workSchedule->end_time) {
            $expectedEnd = Carbon::parse($today->format('Y-m-d') . ' ' . $workSchedule->end_time);
            
            $overtimeStart = $expectedEnd->copy()->addMinutes($settings->overtime_threshold_minutes);
            if ($record->clock_out->greaterThan($overtimeStart)) {
                $record->overtime_minutes = $expectedEnd->diffInMinutes($record->clock_out);
                $record->status = 'overtime';
            } else {
                $earlyDeparture = $expectedEnd->copy()->subMinutes($settings->early_departure_threshold_minutes);
                if ($record->clock_out->lessThan($earlyDeparture)) {
                    $record->early_departure_minutes = $record->clock_out->diffInMinutes($expectedEnd);
                    $record->status = 'early_departure';
                }
            }
        }

        $record->save();
        return $record;
    }

    public function startBreak(User $user, array $options = []): BreakRecord
    {
        $today = Carbon::today();
        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->first();

        if (!$record) {
            throw new Exception('Cannot start break: not currently working');
        }

        $openBreak = BreakRecord::where('attendance_record_id', $record->id)->whereNull('break_end')->first();
        if ($openBreak) {
            throw new Exception('Already on break');
        }

        return BreakRecord::create([
            'attendance_record_id' => $record->id,
            'break_start' => now(),
            'break_type' => $options['break_type'] ?? 'short',
        ]);
    }

    public function endBreak(User $user): BreakRecord
    {
        $today = Carbon::today();
        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if (!$record) {
            throw new Exception('No attendance record found');
        }

        $openBreak = BreakRecord::where('attendance_record_id', $record->id)->whereNull('break_end')->first();
        if (!$openBreak) {
            throw new Exception('Not currently on break');
        }

        $openBreak->break_end = now();
        $openBreak->save();

        return $openBreak;
    }

    public function getTodayStatus(User $user): array
    {
        $today = Carbon::today();
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $today)->first();

        if (!$record) {
            return [
                'status' => 'absent',
                'clock_in' => null,
                'clock_out' => null,
                'hours_worked' => null,
                'worked_seconds' => 0,
                'is_on_break' => false,
                'current_break_start' => null,
                'total_break_minutes' => 0,
                'late_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        $openBreak = BreakRecord::where('attendance_record_id', $record->id)->whereNull('break_end')->first();
        
        $completedBreaks = BreakRecord::where('attendance_record_id', $record->id)->whereNotNull('break_end')->get();
        $totalBreakSecs = $completedBreaks->sum(fn($b) => $b->break_start->diffInSeconds($b->break_end));
        
        if ($openBreak) {
            $totalBreakSecs += $openBreak->break_start->diffInSeconds(now());
        }

        $workedSecs = 0;
        if ($record->clock_in) {
            $end = $record->clock_out ?? now();
            $workedSecs = max(0, $record->clock_in->diffInSeconds($end) - $totalBreakSecs);
        }

        // Format clock times in the employee's effective timezone (not the raw
        // server/canonical timezone), so displays are correct even if
        // APP_TIMEZONE differs from the user's zone.
        $tz = app(\App\Services\TimezoneService::class);

        return [
            'status' => $record->status,
            'clock_in' => $record->clock_in ? $tz->formatForUser($record->clock_in, $user, 'h:i A') : null,
            'clock_out' => $record->clock_out ? $tz->formatForUser($record->clock_out, $user, 'h:i A') : null,
            'hours_worked' => floor($workedSecs / 60), // in minutes for backward compatibility
            'worked_seconds' => $workedSecs,
            'is_on_break' => $openBreak !== null,
            'current_break_start' => $openBreak ? $tz->formatForUser($openBreak->break_start, $user, 'h:i A') : null,
            'total_break_minutes' => floor($totalBreakSecs / 60),
            'late_minutes' => $record->late_minutes,
            'overtime_minutes' => $record->overtime_minutes,
        ];
    }

    public function generateDailyRecords(Carbon $date): void
    {
        $users = User::where('status', 'active')->get();

        foreach ($users as $user) {
            $isWorkingDay = true;
            if (method_exists($user, 'workSchedule') && $user->workSchedule) {
                $isWorkingDay = $user->workSchedule->isWorkingDay($date);
            }

            if (!$isWorkingDay) {
                continue;
            }

            // Check Public Holiday
            $isHoliday = false;
            if (class_exists(HolidayCalendar::class) && method_exists(HolidayCalendar::class, 'isPublicHoliday')) {
                $isHoliday = HolidayCalendar::isPublicHoliday($date, $user->id);
            }

            if ($isHoliday) {
                AttendanceRecord::firstOrCreate(
                    ['user_id' => $user->id, 'date' => $date->toDateString()],
                    ['status' => 'public_holiday']
                );
                continue;
            }

            // Check Time Off
            $onLeave = TimeOffRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $date->toDateString())
                ->whereDate('end_date', '>=', $date->toDateString())
                ->exists();

            if ($onLeave) {
                AttendanceRecord::firstOrCreate(
                    ['user_id' => $user->id, 'date' => $date->toDateString()],
                    ['status' => 'on_leave']
                );
                continue;
            }

            // Check existing record
            $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $date->toDateString())->first();
            
            if ($record) {
                if ($record->clock_in && !$record->clock_out) {
                    $record->update(['status' => 'missing_clock_out']);
                }
            } else {
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                    'status' => 'absent',
                ]);
            }
        }
    }

    public function approveCorrection(AttendanceCorrection $correction, User $approver): void
    {
        DB::transaction(function() use ($correction, $approver) {
            $record = $correction->record;
            if (!$record) {
                $record = AttendanceRecord::create([
                    'user_id' => $correction->user_id,
                    'date' => $correction->correction_date,
                    'status' => 'absent',
                ]);
                $correction->attendance_record_id = $record->id;
            }

            $record->clock_in = $correction->requested_clock_in;
            $record->clock_out = $correction->requested_clock_out;
            $record->edited_by = $approver->id;
            $record->edited_at = now();

            // Recalculate logic
            $total_break_minutes = BreakRecord::where('attendance_record_id', $record->id)->sum('duration_minutes') ?? 0;
            if ($record->clock_in && $record->clock_out) {
                $record->total_minutes_worked = max(0, $record->clock_in->diffInMinutes($record->clock_out) - $total_break_minutes);
                $record->status = 'present'; // Simplistic recalculation
                
                $settings = AttendanceSetting::first() ?? new AttendanceSetting();
                $workSchedule = method_exists($record->employee, 'workSchedule') ? $record->employee->workSchedule : null;
                
                if ($workSchedule && $workSchedule->start_time) {
                    $expectedStart = Carbon::parse($record->date->format('Y-m-d') . ' ' . $workSchedule->start_time);
                    if ($record->clock_in->greaterThan($expectedStart->copy()->addMinutes($settings->grace_period_minutes))) {
                        $record->late_minutes = $expectedStart->diffInMinutes($record->clock_in);
                        $record->status = 'late';
                    }
                }
                
                if ($workSchedule && $workSchedule->end_time) {
                    $expectedEnd = Carbon::parse($record->date->format('Y-m-d') . ' ' . $workSchedule->end_time);
                    if ($record->clock_out->greaterThan($expectedEnd->copy()->addMinutes($settings->overtime_threshold_minutes))) {
                        $record->overtime_minutes = $expectedEnd->diffInMinutes($record->clock_out);
                        $record->status = 'overtime';
                    } elseif ($record->clock_out->lessThan($expectedEnd->copy()->subMinutes($settings->early_departure_threshold_minutes))) {
                        $record->early_departure_minutes = $record->clock_out->diffInMinutes($expectedEnd);
                        $record->status = 'early_departure';
                    }
                }
            }

            $record->save();

            $correction->status = 'approved';
            $correction->reviewed_by = $approver->id;
            $correction->reviewed_at = now();
            $correction->save();
        });
    }

    public function rejectCorrection(AttendanceCorrection $correction, User $approver, string $note): void
    {
        $correction->status = 'rejected';
        $correction->reviewed_by = $approver->id;
        $correction->reviewed_at = now();
        $correction->reviewer_note = $note;
        $correction->save();
        
        $record = $correction->record;
        if ($record && $record->status === 'correction_pending') {
            // Restore previous state if needed, or leave it. 
            // In a real app we might store previous_status. For now, we leave it.
        }
    }
}
