<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyAcknowledgment extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_id', 'user_id', 'assignment_id', 'status', 'viewed_at', 'acknowledged_at',
        'signature_type', 'signature_name', 'signature_data', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function policy()
    {
        return $this->belongsTo(CompanyPolicy::class, 'policy_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignment()
    {
        return $this->belongsTo(PolicyAssignment::class, 'assignment_id');
    }

    public function scopePending(Builder $q): Builder { return $q->where('status', '!=', 'acknowledged'); }
    public function scopeAcknowledged(Builder $q): Builder { return $q->where('status', 'acknowledged'); }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('status', '!=', 'acknowledged')
            ->whereHas('policy', fn ($p) => $p->whereNotNull('effective_date'));
    }
}
