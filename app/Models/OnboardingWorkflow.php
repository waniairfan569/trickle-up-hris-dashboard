<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'department_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function taskTemplates()
    {
        return $this->hasMany(OnboardingTaskTemplate::class, 'workflow_id')->orderBy('sort_order');
    }

    public function onboardings()
    {
        return $this->hasMany(EmployeeOnboarding::class, 'workflow_id');
    }
}
