<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentRequest extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'request_number',
        'user_id',
        'equipment_name',
        'reason',
        'expected_return_date',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'expected_return_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EquipmentRequest $model) {
            if (empty($model->request_number)) {
                $next = (static::max('id') ?? 0) + 1;
                $model->request_number = 'EQ-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
