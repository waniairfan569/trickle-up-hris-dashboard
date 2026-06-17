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
        // Only Check-In (0) and Check-Out (1) affect attendance.
        // Break (2/3) and Overtime (4/5) punches are ignored.
        return match ((int)$this->punch_state) {
            0 => 'clock_in',
            1 => 'clock_out',
            default => 'other',
        };
    }
}
