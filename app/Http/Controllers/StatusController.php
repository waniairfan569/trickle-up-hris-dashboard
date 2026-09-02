<?php

namespace App\Http\Controllers;

use App\Services\SystemHealth;

/** Public system status page — friendly view of the SystemHealth probes. */
class StatusController extends Controller
{
    public function show(SystemHealth $health)
    {
        $checks = $health->checks();

        // Public-friendly subsystems (no timings, no internal detail).
        $systems = [
            ['name' => 'Database', 'status' => $checks['database']['status'] ?? 'unknown'],
            ['name' => 'Cache', 'status' => $checks['cache']['status'] ?? 'unknown'],
            ['name' => 'File storage', 'status' => $checks['storage']['status'] ?? 'unknown'],
            ['name' => 'Background jobs', 'status' => $checks['queue']['status'] ?? 'unknown'],
            ['name' => 'Scheduled tasks', 'status' => $checks['scheduler']['status'] ?? 'unknown'],
        ];

        return view('status', [
            'overall' => $health->overall($checks),
            'systems' => $systems,
        ]);
    }
}
