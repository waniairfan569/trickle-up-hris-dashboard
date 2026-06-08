<?php

namespace App\Traits;

use App\Models\TimeOffAuditLog;

trait LogsTimeOffAudit
{
    protected function logTimeOffAction(
        int $requestId,
        string $action,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        array $previousData = [],
        array $newData = [],
        ?string $note = null
    ): void {
        TimeOffAuditLog::create([
            'time_off_request_id' => $requestId,
            'company_id'          => auth()->user()->company_id,
            'performed_by'        => auth()->id(),
            'action'              => $action,
            'previous_status'     => $previousStatus,
            'new_status'          => $newStatus,
            'previous_data'       => !empty($previousData) ? json_encode($previousData) : null,
            'new_data'            => !empty($newData) ? json_encode($newData) : null,
            'note'                => $note,
            'ip_address'          => request()->ip(),
            'created_at'          => now(),
        ]);
    }
}
