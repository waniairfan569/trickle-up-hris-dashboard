<?php
namespace App\Traits;

use App\Models\ActivityLog;
use App\Events\ActivityLogCreated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public function logActivity(string $action, string $entityType, $entityId, string $description, array $metadata = [])
    {
        $user = Auth::user();

        $log = new ActivityLog([
            'company_id' => $user ? $user->company_id : 1, // Fallback for tests/seeders
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => !empty($metadata) ? $metadata : null,
            'ip_address' => Request::ip()
        ]);
        $log->save();

        if ($user) {
            broadcast(new ActivityLogCreated($log, $user->company_id))->toOthers();
        }
    }
}
