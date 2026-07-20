<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZktecoRawPunch extends Model
{
    use BelongsToTenant;
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
        // Punch state -> label. Only clock_in / clock_out drive attendance;
        // break_* and overtime_* are recorded for reference but ignored by
        // processPunch(). Unknown states stay 'other' (NOT a clock-in) so a
        // device sending an unexpected state can't create phantom attendance.
        // NOTE: the strings 'clock_in'/'clock_out' must stay exactly as-is —
        // processPunch() matches on them.
        return match ((int) $this->punch_state) {
            0 => 'clock_in',
            1 => 'clock_out',
            4 => 'break_out',
            5 => 'break_in',
            6 => 'overtime_in',   // SpeedFace-V5L
            7 => 'overtime_out',  // SpeedFace-V5L
            default => 'other',
        };
    }
}
