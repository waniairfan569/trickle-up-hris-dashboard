<?php

namespace App\Console\Commands;

use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:generate-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily attendance records for missing clock-ins, public holidays, and leaves.';

    /**
     * Execute the console command.
     */
    public function handle(AttendanceService $service)
    {
        $this->info('Generating attendance records for yesterday...');
        $service->generateDailyRecords(Carbon::yesterday());
        $this->info('Done.');
    }
}
