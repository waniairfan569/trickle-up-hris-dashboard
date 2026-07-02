<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'break_start',
        'break_end',
        'duration_minutes',
        'break_type',
    ];

    protected $casts = [
        'break_start' => 'datetime',
        'break_end' => 'datetime',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->break_end && $model->duration_minutes === null) {
                // Carbon 3 diffs are signed; earlier->diff(later) keeps it positive.
                $model->duration_minutes = max(0, (int) round($model->break_start->diffInMinutes($model->break_end)));
            }
        });
    }

    public function record()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }
}
