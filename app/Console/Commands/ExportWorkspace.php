<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\WorkspaceExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * GDPR data portability — export one workspace's data to a JSON file.
 *   php artisan workspace:export <id|slug>
 */
class ExportWorkspace extends Command
{
    protected $signature = 'workspace:export {tenant : Tenant id or slug} {--disk=local} {--path=}';

    protected $description = 'Export all of a workspace\'s data to a JSON file (GDPR data portability).';

    public function handle(WorkspaceExporter $exporter): int
    {
        $tenant = $this->resolveTenant($this->argument('tenant'));
        if (!$tenant) {
            $this->error('No workspace found for "' . $this->argument('tenant') . '".');

            return self::FAILURE;
        }

        $json = $exporter->toJson($tenant);
        $path = $this->option('path') ?: 'exports/workspace-' . $tenant->slug . '-' . now()->format('Y-m-d_His') . '.json';
        Storage::disk($this->option('disk'))->put($path, $json);

        $counts = $exporter->export($tenant)['record_counts'];
        $this->info('Exported ' . array_sum($counts) . ' records across ' . count($counts) . ' tables.');
        $this->line('Saved → ' . $this->option('disk') . ':' . $path . ' (' . round(strlen($json) / 1024, 1) . ' KB)');

        return self::SUCCESS;
    }

    private function resolveTenant(string $key): ?Tenant
    {
        return is_numeric($key)
            ? Tenant::find($key)
            : Tenant::where('slug', $key)->first();
    }
}
