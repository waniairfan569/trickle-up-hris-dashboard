<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeOnboarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workflow_id',
        'started_at',
        'completed_at',
        'triggered_by',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function workflow()
    {
        return $this->belongsTo(OnboardingWorkflow::class, 'workflow_id');
    }

    public function tasks()
    {
        return $this->hasMany(OnboardingTask::class, 'employee_onboarding_id');
    }

    public function triggerer()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function progressPercent(): int
    {
        $total = $this->tasks()->count();
        if ($total === 0) return 0;
        
        $completed = $this->tasks()->whereIn('status', ['completed', 'skipped'])->count();
        return (int) round(($completed / $total) * 100);
    }

    public function isOverdue(): bool
    {
        return $this->tasks()->where('status', 'pending')->where('due_date', '<', today())->exists();
    }
}
