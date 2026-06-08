<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZktecoRawPunch extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'zkteco_uid',
        'zkteco_employee_id',
        'user_id',
        'punched_at',
        'punch_state',
        'verify_type',
        'is_processed',
        'is_duplicate',
        'processed_at',
    ];

    protected $casts = [
        'punched_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean',
        'is_duplicate' => 'boolean',
    ];

    public function device()
    {
        return $this->belongsTo(ZktecoDevice::class, 'device_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getPunchTypeLabelAttribute()
    {
        return match ((int)$this->punch_state) {
            0 => 'clock_in',
            1 => 'clock_out',
            4 => 'break_out',
            5 => 'break_in',
            default => 'clock_in',
        };
    }
}
