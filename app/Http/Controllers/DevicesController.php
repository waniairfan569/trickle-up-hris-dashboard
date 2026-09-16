<?php

namespace App\Http\Controllers;

use App\Models\ZktecoDevice;
use App\Models\ZktecoUnmapped;
use Carbon\Carbon;

/**
 * Devices hub — one landing page with a tile per hardware integration (today
 * just ZKTeco biometric devices), so the sidebar carries a single "Devices"
 * link instead of a dropdown. Admin-only, same as the pages it links to.
 */
class DevicesController extends Controller
{
    public function index()
    {
        $unmapped = ZktecoUnmapped::unresolved()->count();
        $lastSync = ZktecoDevice::max('last_synced_at');

        $tiles = [[
            'route' => 'zkteco.dashboard',
            'icon' => 'fingerprint',
            'tone' => 'brand',
            'title' => 'ZKTeco Devices',
            'text' => 'Biometric clock-in terminals — sync punches, map device users to employees.',
            'stat' => ZktecoDevice::where('is_active', true)->count(),
            'stat_label' => 'active devices',
            'meta' => $lastSync ? 'Synced ' . Carbon::parse($lastSync)->diffForHumans(null, true) . ' ago' : 'Never synced',
            // Unmapped device users are punches nobody is credited for — surface them.
            'badge' => $unmapped,
        ]];

        return view('devices.index', compact('tiles'));
    }
}
