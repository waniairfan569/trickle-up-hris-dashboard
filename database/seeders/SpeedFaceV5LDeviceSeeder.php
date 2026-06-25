<?php

namespace Database\Seeders;

use App\Models\ZktecoDevice;
use Illuminate\Database\Seeder;

/**
 * Adds the ZKTeco SpeedFace-V5L (face recognition) as a second device,
 * alongside the existing K50. Idempotent — keyed on ip_address, so running
 * it repeatedly won't create duplicates and never touches the K50.
 */
class SpeedFaceV5LDeviceSeeder extends Seeder
{
    public function run(): void
    {
        ZktecoDevice::firstOrCreate(
            ['ip_address' => '192.168.18.78'],
            [
                'name' => 'SpeedFace-V5L',
                'port' => 4370,
                'is_active' => true,
                'last_sync_status' => 'never',
                'total_records_synced' => 0,
            ]
        );
    }
}
