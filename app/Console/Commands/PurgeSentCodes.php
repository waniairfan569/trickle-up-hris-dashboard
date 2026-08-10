<?php

namespace App\Console\Commands;

use App\Models\CodeRequest;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * One-time login codes / credentials shouldn't be retained forever. Once the
 * employee has had time to use a sent code, null out the stored value so it no
 * longer sits in the database or the admin history.
 *
 *   php artisan code-requests:purge-codes            # default 7 days
 *   php artisan code-requests:purge-codes --days=3 --dry-run
 */
class PurgeSentCodes extends Command
{
    protected $signature = 'code-requests:purge-codes {--days=7} {--dry-run}';

    protected $description = 'Redact stored login codes once the employee has had time to use them.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::now()->subDays($days);

        $query = CodeRequest::where('status', 'code_sent')
            ->whereNotNull('code_provided')
            ->where('code_sent_at', '<', $cutoff);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would redact {$count} stored code(s) sent before {$cutoff->toDateTimeString()}.");
            return self::SUCCESS;
        }

        // Direct update — null needs no encryption and skips the model cast.
        $affected = $query->update(['code_provided' => null]);
        $this->info("Redacted {$affected} stored code(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
