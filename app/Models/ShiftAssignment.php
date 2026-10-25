<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class ShiftAssignment extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'user_id',
        'shift_id',
        'assigned_by',
        'covers_user_id',
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

    /**
     * Who this assignment stands in for, when it was made to fill a hole left
     * by somebody's leave. Null on every ordinary assignment, which is all of
     * the ones that existed before cover was recordable.
     */
    public function coveringFor()
    {
        return $this->belongsTo(User::class, 'covers_user_id');
    }

    public function scopeCover($query)
    {
        return $query->whereNotNull('covers_user_id');
    }
}
