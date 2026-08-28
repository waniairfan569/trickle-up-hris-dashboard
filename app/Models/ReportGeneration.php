<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReportGeneration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'generated_by', 'report_scope', 'report_type', 'employee_id', 'employee_name',
        'month', 'year', 'half', 'date_from', 'date_to',
        'period_label', 'output', 'downloads_count', 'last_downloaded_at', 'note',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'date_from' => 'date',
        'date_to' => 'date',
        'downloads_count' => 'integer',
        'last_downloaded_at' => 'datetime',
    ];

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** Human label for the "who" of the report. */
    public function getScopeLabelAttribute(): string
    {
        return $this->report_scope === 'all'
            ? 'All employees'
            : ($this->employee_name ?: optional($this->employee)->full_name ?: 'Employee');
    }

    /** Human label for the period type. */
    public function getTypeLabelAttribute(): string
    {
        return [
            'monthly'  => 'Monthly',
            'yearly'   => 'Full year',
            'mid_year' => 'Mid-year',
            'custom'   => 'Custom range',
        ][$this->report_type] ?? ucfirst($this->report_type);
    }

    /** True once the report has actually been downloaded (not just previewed). */
    public function getWasDownloadedAttribute(): bool
    {
        return $this->downloads_count > 0;
    }
}
