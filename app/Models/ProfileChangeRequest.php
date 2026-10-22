<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A change an employee has asked to make to their own record, which HR has to
 * approve before it is written.
 *
 * The field decides whether anything comes through here: only fields a
 * workspace has marked "HR approves first" create one.
 */
class ProfileChangeRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'field_id', 'old_value', 'new_value',
        'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function field()
    {
        return $this->belongsTo(ProfileField::class, 'field_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Something readable for a list, whatever the field's type. */
    public function label(): string
    {
        return $this->field->name ?: $this->field->key;
    }
}
