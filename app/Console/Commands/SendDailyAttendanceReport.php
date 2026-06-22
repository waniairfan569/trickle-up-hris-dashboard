<?php

namespace App\Console\Commands;

use App\Models\AttendanceReportSettings;
use App\Services\AttendanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyAttendanceReport extends Command
{
    protected $signature = 'attendance:send-daily-report {--date= : Specific date (Y-m-d)} {--force : Send even on holidays/weekends}';

    protected $description = 'Send the daily attendance report email to the configured recipients.';

    public function handle(AttendanceReportService $service): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        // --force bypasses the working-day check by treating it as a manual run.
        $result = $service->sendDailyReport($date, (bool) $this->option('force'));

        if (!empty($result['skipped'])) {
            $this->warn('Skipped: ' . $result['reason']);
            return self::SUCCESS;
        }

        $this->info('Sent to ' . $result['recipients'] . ' recipient(s)' .
            (count($result['emails']) ? ': ' . implode(', ', $result['emails']) : '.'));

        return self::SUCCESS;
    }
}
