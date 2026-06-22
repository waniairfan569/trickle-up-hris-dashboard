<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_id', 'assigned_to_type', 'assigned_to_id', 'assigned_by',
        'assigned_at', 'deadline', 'notification_sent', 'reminder_sent_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'deadline' => 'date',
        'notification_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
    ];

    public function policy()
    {
        return $this->belongsTo(CompanyPolicy::class, 'policy_id');
    }

    public function getLabelAttribute(): string
    {
        return match ($this->assigned_to_type) {
            'all' => 'Whole company',
            'department' => 'Dept: ' . (optional(Department::find($this->assigned_to_id))->name ?? '#' . $this->assigned_to_id),
            'user' => optional(User::find($this->assigned_to_id))->full_name ?? '#' . $this->assigned_to_id,
            default => $this->assigned_to_type,
        };
    }
}
