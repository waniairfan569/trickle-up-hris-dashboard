<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Verify a filesystem disk works end-to-end — write, read, delete, and (where
 * supported) a temporary signed URL. Use it to confirm S3 is wired up before
 * flipping FILESYSTEM_DISK=s3 in production:
 *   php artisan storage:test            # the default disk
 *   php artisan storage:test s3
 */
class StorageTest extends Command
{
    protected $signature = 'storage:test {disk? : Disk name (defaults to the configured default)}';

    protected $description = 'Write / read / delete a probe file on a disk to verify it works (great for checking S3).';

    public function handle(): int
    {
        $disk = $this->argument('disk') ?: config('filesystems.default');
        $driver = config("filesystems.disks.{$disk}.driver", '?');

        $this->line('');
        $this->info("Testing disk '{$disk}' (driver: {$driver})");
        $this->line('  default disk (FILESYSTEM_DISK): ' . config('filesystems.default'));

        $path = 'health/storage-test-' . Str::random(10) . '.txt';
        $payload = 'ok ' . now()->toIso8601String();

        try {
            $fs = Storage::disk($disk);

            $fs->put($path, $payload);
            $this->line('  write  ✔');

            $read = $fs->get($path);
            if ($read !== $payload) {
                throw new \RuntimeException('read back did not match what was written');
            }
            $this->line('  read   ✔');

            // Temporary signed URL — supported on S3, not on the plain local driver.
            try {
                $url = $fs->temporaryUrl($path, now()->addMinutes(5));
                $this->line('  signed url ✔  ' . Str::limit($url, 70));
            } catch (\Throwable $e) {
                $this->line('  signed url — not supported on this driver (fine for local)');
            }

            $fs->delete($path);
            $this->line('  delete ✔');
        } catch (\Throwable $e) {
            $this->error('  FAILED: ' . $e->getMessage());
            $this->line('  Check the disk credentials/bucket. For S3, run: composer require league/flysystem-aws-s3-v3');

            return self::FAILURE;
        }

        $this->info("Disk '{$disk}' is working.");

        return self::SUCCESS;
    }
}
