<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingTaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'title',
        'description',
        'assigned_to_role',
        'due_days_from_start',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'due_days_from_start' => 'integer',
        'sort_order' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(OnboardingWorkflow::class, 'workflow_id');
    }
}
