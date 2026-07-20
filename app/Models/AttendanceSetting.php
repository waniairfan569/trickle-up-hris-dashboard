<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_entity_id',
        'grace_period_minutes',
        'overtime_threshold_minutes',
        'early_departure_threshold_minutes',
        'allow_break_tracking',
        'allow_gps_capture',
        'allow_manual_entry',
        'max_break_duration_minutes',
    ];

    protected $casts = [
        'allow_break_tracking' => 'boolean',
        'allow_gps_capture' => 'boolean',
        'allow_manual_entry' => 'boolean',
    ];

    public function companyEntity()
    {
        return $this->belongsTo(CompanyEntity::class);
    }
}
