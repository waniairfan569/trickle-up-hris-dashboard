<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id', 'assigned_to_type', 'assigned_to_id', 'assigned_by',
        'assigned_at', 'deadline_override', 'notification_sent',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'deadline_override' => 'datetime',
        'notification_sent' => 'boolean',
    ];

    public function form()
    {
        return $this->belongsTo(CompanyForm::class, 'form_id');
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
