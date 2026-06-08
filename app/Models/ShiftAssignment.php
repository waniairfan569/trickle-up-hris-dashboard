<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'shift_id',
        'assigned_by',
        'assignment_type',
        'date',
        'recurring_start_date',
        'recurring_end_date',
        'recurring_days',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'recurring_start_date' => 'date',
        'recurring_end_date' => 'date',
        'recurring_days' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
