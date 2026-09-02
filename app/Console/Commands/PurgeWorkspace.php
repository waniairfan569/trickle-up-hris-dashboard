<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WorkspaceExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GDPR right-to-erasure — permanently delete one workspace and ALL its data.
 * Exports a backup JSON first (unless --no-export). Destructive; guarded.
 *   php artisan workspace:purge <id|slug> [--force] [--no-export]
 */
class PurgeWorkspace extends Command
{
    protected $signature = 'workspace:purge {tenant : Tenant id or slug} {--force : Skip confirmation} {--no-export : Do not save a backup export first}';

    protected $description = 'Permanently delete a workspace and all its data (exports a backup first).';

    public function handle(WorkspaceExporter $exporter): int
    {
        $tenant = is_numeric($this->argument('tenant'))
            ? Tenant::find($this->argument('tenant'))
            : Tenant::where('slug', $this->argument('tenant'))->first();

        if (!$tenant) {
            $this->error('No workspace found for "' . $this->argument('tenant') . '".');

            return self::FAILURE;
        }

        // Guard the original/default workspace against accidental erasure.
        if ($tenant->slug === 'trickle-up') {
            $this->error('Refusing to purge the primary workspace (trickle-up).');

            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("Permanently DELETE workspace \"{$tenant->name}\" (#{$tenant->id}) and ALL its data? This cannot be undone.")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        // Safety export first.
        if (!$this->option('no-export')) {
            $path = 'exports/purged-' . $tenant->slug . '-' . now()->format('Y-m-d_His') . '.json';
            Storage::disk('local')->put($path, $exporter->toJson($tenant));
            $this->line('Backup export saved → local:' . $path);
        }

        $name = $tenant->name;
        $deleted = $exporter->purge($tenant);
        $total = array_sum($deleted);

        Log::warning("Workspace purged: {$name} (#{$tenant->id}) — {$total} rows deleted.");
        $this->info("Purged \"{$name}\" — {$total} rows across " . count($deleted) . ' tables deleted.');

        return self::SUCCESS;
    }
}
