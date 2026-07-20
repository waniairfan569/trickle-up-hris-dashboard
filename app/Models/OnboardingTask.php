<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTask extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'employee_onboarding_id',
        'task_template_id',
        'assigned_to_user_id',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
        'completed_by',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function onboarding()
    {
        return $this->belongsTo(EmployeeOnboarding::class, 'employee_onboarding_id');
    }

    public function template()
    {
        return $this->belongsTo(OnboardingTaskTemplate::class, 'task_template_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')->where('due_date', '<', today());
    }
}
