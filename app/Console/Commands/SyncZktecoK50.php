<?php

namespace App\Console\Commands;

use App\Models\ZktecoDevice;
use App\Services\ZktecoK50Service;
use Illuminate\Console\Command;
use Exception;

class SyncZktecoK50 extends Command
{
    protected $signature = 'zkteco:sync-k50 {--device-id= : Specific device ID to sync}';
    protected $description = 'Sync attendance records from ZKTeco K50 biometric devices';

    public function handle(ZktecoK50Service $zktecoService)
    {
        $deviceId = $this->option('device-id');

        // Only PULL devices are dialed here. Push (ADMS) devices report to
        // /iclock on their own and must not be marked "failed" by the puller.
        $query = ZktecoDevice::where('is_active', true)
            ->where(fn ($q) => $q->where('connection_mode', '!=', 'push')->orWhereNull('connection_mode'));

        if ($deviceId) {
            $query->where('id', $deviceId);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->info('No active ZKTeco devices found.');
            return;
        }

        foreach ($devices as $device) {
            $this->info("Syncing device: {$device->name} ({$device->ip_address}:{$device->port})");

            try {
                $result = $zktecoService->syncDevice($device);
                
                $this->table(
                    ['Records Pulled', 'Imported', 'Duplicates Skipped', 'Unmapped Employees'],
                    [[
                        $result['synced'],
                        $result['imported'],
                        $result['duplicates'],
                        $result['unmapped']
                    ]]
                );

                $this->info('Device synced successfully.');
            } catch (Exception $e) {
                $this->error("Failed to sync device {$device->name}: " . $e->getMessage());
            }
        }
    }
}
