<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZktecoDevice extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'name',
        'serial_number',
        'model',
        'firmware',
        'platform',
        'capabilities',
        'described_at',
        'connection_mode',
        'ip_address',
        'port',
        'timezone',
        'is_active',
        'last_synced_at',
        'last_contact_at',
        'silent_since',
        'silence_notified_at',
        'last_sync_status',
        'last_sync_message',
        'total_records_synced',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'silent_since' => 'datetime',
        'silence_notified_at' => 'datetime',
        'described_at' => 'datetime',
        'capabilities' => 'array',
    ];

    public function rawPunches()
    {
        return $this->hasMany(ZktecoRawPunch::class, 'device_id');
    }

    public function getStatusBadgeAttribute()
    {
        return match ($this->last_sync_status) {
            'success' => 'bg-green-100 text-green-700',
            'failed'  => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-700',
        };
    }
}
