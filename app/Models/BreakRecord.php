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
                $model->duration_minutes = $model->break_end->diffInMinutes($model->break_start);
            }
        });
    }

    public function record()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }
}
