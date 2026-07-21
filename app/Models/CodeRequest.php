<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'request_number',
        'employee_id',
        'tool_name',
        'message',
        'status',
        'code_provided',
        'code_sent_at',
        'code_expires_note',
        'rejection_reason',
        'responded_by',
    ];

    protected $casts = [
        'code_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CodeRequest $model) {
            if (empty($model->request_number)) {
                $next = (static::max('id') ?? 0) + 1;
                $model->request_number = 'CR-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
