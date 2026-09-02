<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Self-contained daily backup — no external package. Dumps the MySQL database
 * (gzip) and, optionally, zips uploaded files, writes them to the configured
 * disk, copies them off-site when an upload disk is set, and prunes old copies.
 *
 * Cross-platform: mysqldump runs via Symfony Process (password passed through
 * MYSQL_PWD, never on the command line); gzip is done in PHP.
 */
class RunBackup extends Command
{
    protected $signature = 'backup:run {--keep= : Override retention days} {--no-files : Skip the uploaded-files archive}';

    protected $description = 'Back up the database (+ uploaded files) to the backup disk, with off-site copy and pruning.';

    public function handle(): int
    {
        $disk = config('backup.disk', 'local');
        $keep = (int) ($this->option('keep') ?: config('backup.keep_days', 14));
        $stamp = now()->format('Y-m-d_His');
        $written = [];

        // ---- 1. Database dump ------------------------------------------------
        try {
            $sqlGz = $this->dumpDatabase();
            $dbPath = "backups/db-{$stamp}.sql.gz";
            Storage::disk($disk)->put($dbPath, $sqlGz);
            $written[$dbPath] = strlen($sqlGz);
            $this->info('Database dumped → ' . $dbPath . ' (' . $this->human(strlen($sqlGz)) . ')');
        } catch (\Throwable $e) {
            $this->error('Database backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        // ---- 2. Uploaded files archive --------------------------------------
        if (config('backup.include_files', true) && !$this->option('no-files')) {
            $filesPath = $this->archiveFiles($stamp, $disk, $written);
            if ($filesPath === null) {
                $this->warn('Files archive skipped (nothing to archive or ZipArchive unavailable).');
            }
        }

        // ---- 3. Off-site copy -----------------------------------------------
        $uploadDisk = config('backup.upload_disk');
        if ($uploadDisk) {
            foreach (array_keys($written) as $path) {
                try {
                    Storage::disk($uploadDisk)->put($path, Storage::disk($disk)->get($path));
                    $this->info("Copied off-site → {$uploadDisk}:{$path}");
                } catch (\Throwable $e) {
                    $this->error("Off-site copy failed for {$path}: " . $e->getMessage());
                }
            }
        }

        // ---- 4. Prune old backups on the primary disk -----------------------
        $this->prune($disk, $keep);

        $this->info('Backup complete.');

        return self::SUCCESS;
    }

    /** Run mysqldump and return the gzipped SQL. */
    private function dumpDatabase(): string
    {
        $c = config('database.connections.' . config('database.default'));

        $process = new Process([
            config('backup.mysqldump_path', 'mysqldump'),
            '--host=' . ($c['host'] ?? '127.0.0.1'),
            '--port=' . ($c['port'] ?? 3306),
            '--user=' . ($c['username'] ?? 'root'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=' . ($c['charset'] ?? 'utf8mb4'),
            $c['database'],
        ], base_path(), ['MYSQL_PWD' => (string) ($c['password'] ?? '')], null, 600);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump exited non-zero');
        }

        $gz = gzencode($process->getOutput(), 6);
        if ($gz === false) {
            throw new \RuntimeException('Failed to gzip the SQL dump.');
        }

        return $gz;
    }

    /** Zip storage/app/public into the backup disk. Returns the stored path or null. */
    private function archiveFiles(string $stamp, string $disk, array &$written): ?string
    {
        $source = storage_path('app/public');
        if (!is_dir($source) || !class_exists(\ZipArchive::class)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bkp') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)
        );
        $count = 0;
        foreach ($files as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($source) + 1));
                $count++;
            }
        }
        $zip->close();

        if ($count === 0) {
            @unlink($tmp);
            return null;
        }

        $path = "backups/files-{$stamp}.zip";
        Storage::disk($disk)->put($path, file_get_contents($tmp));
        $written[$path] = filesize($tmp);
        @unlink($tmp);

        $this->info("Files archived → {$path} ({$count} files, " . $this->human($written[$path]) . ')');

        return $path;
    }

    /** Delete backups older than $keep days on $disk. */
    private function prune(string $disk, int $keep): void
    {
        if ($keep <= 0) {
            return;
        }

        $cutoff = now()->subDays($keep)->getTimestamp();
        $removed = 0;
        foreach (Storage::disk($disk)->files('backups') as $file) {
            try {
                if (Storage::disk($disk)->lastModified($file) < $cutoff) {
                    Storage::disk($disk)->delete($file);
                    $removed++;
                }
            } catch (\Throwable $e) {
                // ignore individual prune failures
            }
        }

        if ($removed > 0) {
            $this->info("Pruned {$removed} backup(s) older than {$keep} day(s).");
        }
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
