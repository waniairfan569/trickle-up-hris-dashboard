<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZktecoUnmapped extends Model
{
    use HasFactory;

    protected $table = 'zkteco_unmapped';

    protected $fillable = [
        'device_id',
        'zkteco_uid',
        'zkteco_employee_id',
        'punch_count',
        'first_seen',
        'last_seen',
        'is_resolved',
        'resolved_user_id',
        'resolved_at',
    ];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'resolved_at' => 'datetime',
        'is_resolved' => 'boolean',
    ];

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function device()
    {
        return $this->belongsTo(ZktecoDevice::class, 'device_id');
    }

    public function resolvedUser()
    {
        return $this->belongsTo(User::class, 'resolved_user_id');
    }
}
