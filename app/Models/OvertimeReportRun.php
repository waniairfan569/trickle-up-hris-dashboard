<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** A logged generation/export of the approved-overtime report (finance trail). */
class OvertimeReportRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'period_from', 'period_to', 'label', 'format',
        'entry_count', 'employee_count', 'total_hours', 'generated_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'total_hours' => 'decimal:2',
    ];

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
