<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeOffAuditLog extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'time_off_request_id',
        'company_id',
        'performed_by',
        'action',
        'previous_status',
        'new_status',
        'previous_data',
        'new_data',
        'note',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'previous_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function timeOffRequest(): BelongsTo
    {
        return $this->belongsTo(TimeOffRequest::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForRequest($query, $id)
    {
        return $query->where('time_off_request_id', $id);
    }
}
