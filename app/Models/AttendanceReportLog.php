<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendanceReportLog extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'report_date', 'sent_at', 'sent_to', 'total_employees', 'present_count',
        'late_count', 'absent_count', 'on_leave_count', 'status', 'error_message',
        'triggered_by', 'triggered_by_user_id',
    ];

    protected $casts = [
        'report_date' => 'date',
        'sent_at' => 'datetime',
        'sent_to' => 'array',
    ];

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function scopeForDate(Builder $q, Carbon $date): Builder
    {
        return $q->whereDate('report_date', $date->toDateString());
    }

    public function scopeSent(Builder $q): Builder
    {
        return $q->where('status', 'sent');
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', 'failed');
    }
}
