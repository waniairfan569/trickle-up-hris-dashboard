<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Restore a database (and optionally uploaded files) from a `backup:run`
 * archive. Destructive — it overwrites the current database — so it requires
 * confirmation, and --force in production. Pair with the runbook at
 * docs/backup-restore.md and run a drill regularly so restores are proven.
 */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore
        {--file= : DB backup path on the disk (default: newest backups/db-*.sql.gz)}
        {--disk= : Filesystem disk to read from (default: config backup.disk)}
        {--with-files : Also restore the matching files-*.zip into storage/app/public}
        {--list : List available backups and exit}
        {--force : Skip the confirmation prompt (required in production)}';

    protected $description = 'Restore the database (+ optional files) from a backup archive. DESTRUCTIVE.';

    public function handle(): int
    {
        $disk = $this->option('disk') ?: config('backup.disk', 'local');
        $dbFiles = collect(Storage::disk($disk)->files('backups'))
            ->filter(fn ($p) => str_contains($p, '/db-') && str_ends_with($p, '.sql.gz'))
            ->sortDesc()->values();

        if ($this->option('list') || $dbFiles->isEmpty()) {
            if ($dbFiles->isEmpty()) {
                $this->warn("No database backups found on disk [{$disk}] under backups/.");

                return self::FAILURE;
            }
            $this->info("Available DB backups on [{$disk}]:");
            foreach ($dbFiles as $f) {
                $this->line('  ' . $f . '  (' . $this->human((int) Storage::disk($disk)->size($f)) . ')');
            }

            return self::SUCCESS;
        }

        $file = $this->option('file') ?: $dbFiles->first();
        if (!Storage::disk($disk)->exists($file)) {
            $this->error("Backup file not found on [{$disk}]: {$file}");

            return self::FAILURE;
        }

        $db = config('database.connections.' . config('database.default') . '.database');
        $this->warn("This will OVERWRITE the current database ({$db}) with: {$file}");

        if (!$this->option('force')) {
            if (app()->environment('production')) {
                $this->error('Refusing to restore in production without --force.');

                return self::FAILURE;
            }
            if (!$this->confirm('Continue and overwrite the current database?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        // ---- 1. Restore the database ----------------------------------------
        try {
            $gz = Storage::disk($disk)->get($file);
            $sql = gzdecode($gz);
            if ($sql === false) {
                throw new \RuntimeException('Could not gunzip the backup (corrupt or not gzipped).');
            }
            $this->restoreDatabase($sql);
            $this->info('Database restored from ' . $file . '.');
        } catch (\Throwable $e) {
            $this->error('Database restore failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        // ---- 2. Restore uploaded files (optional) ---------------------------
        if ($this->option('with-files')) {
            $stamp = str_replace(['backups/db-', '.sql.gz'], '', $file);
            $filesPath = "backups/files-{$stamp}.zip";
            if (Storage::disk($disk)->exists($filesPath)) {
                $this->restoreFiles($disk, $filesPath);
            } else {
                $this->warn("No matching files archive ({$filesPath}); skipped.");
            }
        }

        $this->info('Restore complete. Run `php artisan optimize:clear` and verify the app.');

        return self::SUCCESS;
    }

    /** Pipe SQL into the mysql client (password via MYSQL_PWD, never argv). */
    private function restoreDatabase(string $sql): void
    {
        $c = config('database.connections.' . config('database.default'));

        $process = new Process([
            config('backup.mysql_path', 'mysql'),
            '--host=' . ($c['host'] ?? '127.0.0.1'),
            '--port=' . ($c['port'] ?? 3306),
            '--user=' . ($c['username'] ?? 'root'),
            '--default-character-set=' . ($c['charset'] ?? 'utf8mb4'),
            $c['database'],
        ], base_path(), ['MYSQL_PWD' => (string) ($c['password'] ?? '')], $sql, 1200);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysql exited non-zero');
        }
    }

    /** Extract a files-*.zip archive back into storage/app/public. */
    private function restoreFiles(string $disk, string $zipPath): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->warn('ZipArchive not available — files not restored.');

            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rst') . '.zip';
        file_put_contents($tmp, Storage::disk($disk)->get($zipPath));

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            $this->warn('Could not open files archive; skipped.');

            return;
        }
        $dest = storage_path('app/public');
        @mkdir($dest, 0775, true);
        $zip->extractTo($dest);
        $count = $zip->numFiles;
        $zip->close();
        @unlink($tmp);

        $this->info("Files restored → storage/app/public ({$count} entries).");
    }

    private function human(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($u) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $u[$i];
    }
}
