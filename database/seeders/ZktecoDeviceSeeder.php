<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ZktecoDevice;
use Illuminate\Database\Seeder;

class ZktecoDeviceSeeder extends Seeder
{
    public function run()
    {
        ZktecoDevice::create([
            'name' => 'Main Entrance — K50',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
            'last_sync_status' => 'never',
        ]);

        $mappings = [
            'sara.rahman@company.com' => ['uid' => 1, 'eid' => '1'],
            'hamid.malik@company.com' => ['uid' => 2, 'eid' => '2'],
            'dave.khan@company.com' => ['uid' => 3, 'eid' => '3'],
            'ali.javed@company.com' => ['uid' => 4, 'eid' => '4'],
        ];

        foreach ($mappings as $email => $mapping) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update([
                    'zkteco_uid' => $mapping['uid'],
                    'zkteco_employee_id' => $mapping['eid'],
                ]);
            }
        }
        
        $nida = User::where('email', 'nida.zahra@company.com')->first();
        if ($nida) {
            $nida->update([
                'zkteco_uid' => null,
                'zkteco_employee_id' => null,
            ]);
        }
    }
}
