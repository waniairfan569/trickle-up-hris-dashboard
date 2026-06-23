<?php

namespace App\Console\Commands;

use App\Mail\UpcomingEventsMail;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendUpcomingEventReminders extends Command
{
    protected $signature = 'events:send-reminders {--date= : Target event date as Y-m-d (defaults to tomorrow)} {--dry : Report only, do not send}';

    protected $description = 'Email all active employees about events happening on the target date (default: tomorrow).';

    public function handle(): int
    {
        $target = $this->option('date') ? Carbon::parse($this->option('date')) : now()->addDay()->startOfDay();
        $dry = (bool) $this->option('dry');

        // Events starting on the target date, plus multi-day events that span it.
        $events = Event::active()
            ->where(function ($q) use ($target) {
                $q->whereDate('date', $target->toDateString())
                    ->orWhere(function ($q2) use ($target) {
                        $q2->whereNotNull('end_date')
                            ->whereDate('date', '<=', $target->toDateString())
                            ->whereDate('end_date', '>=', $target->toDateString());
                    });
            })
            ->orderBy('date')
            ->get();

        if ($events->isEmpty()) {
            $this->info("No events on {$target->toDateString()} — nothing to send.");

            return self::SUCCESS;
        }

        $employees = User::where('account_status', 'active')->whereNotNull('email')->get();

        if ($dry) {
            $this->info("[dry-run] {$events->count()} event(s) on {$target->toDateString()} — would email {$employees->count()} employee(s).");

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($employees as $employee) {
            try {
                Mail::to($employee->email)->send(new UpcomingEventsMail($events, $target->copy(), $employee));
                $sent++;
            } catch (\Throwable $ex) {
                report($ex);
            }
        }

        $this->info("Sent {$events->count()} event reminder(s) for {$target->toDateString()} to {$sent} employee(s).");

        return self::SUCCESS;
    }
}
