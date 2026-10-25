<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A change to one person's leave allowance under one policy, and the day it
 * took effect — so a year that was served at two different rates can be worked
 * out instead of being treated as though it was all at the latest one.
 */
class LeaveAllowanceChange extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'policy_id',
        'previous_days', 'new_days', 'effective_from', 'changed_by', 'note',
    ];

    protected $casts = [
        'previous_days' => 'float',
        'new_days' => 'float',
        'effective_from' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }
}
