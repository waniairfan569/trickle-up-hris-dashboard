<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A check carried out on somebody: right to work, DBS, a licence.
 *
 * Deliberately not tied to a provider. A person looking at a passport and an
 * API returning a result both end up as the same record — what was checked,
 * what it said, who says so, and when it stops counting.
 */
class EmployeeCheck extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'status', 'reference',
        'verified_by_name', 'checked_on', 'expires_on', 'notes',
        'document_id', 'recorded_by',
    ];

    protected $casts = [
        'checked_on' => 'date',
        'expires_on' => 'date',
    ];

    /** The kinds of check a workspace can record. */
    public const TYPES = [
        'right_to_work' => 'Right to work',
        'dbs'           => 'DBS / criminal record',
        'identity'      => 'Identity',
        'qualification' => 'Qualification',
        'registration'  => 'Professional registration',
        'driving'       => 'Driving licence',
        'reference'     => 'Reference',
        'medical'       => 'Medical clearance',
        'other'         => 'Other',
    ];

    public const STATUSES = [
        'pending' => 'Waiting',
        'passed'  => 'Passed',
        'failed'  => 'Not passed',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function document()
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Past its date. A check with no expiry never reaches this. */
    public function hasExpired(): bool
    {
        return $this->expires_on && $this->expires_on->isPast();
    }

    /** Still valid, but not for much longer. */
    public function expiresWithin(int $days): bool
    {
        return $this->expires_on
            && ! $this->hasExpired()
            && $this->expires_on->lte(now()->addDays($days));
    }

    /**
     * What this check is, read as one word — which is what a list wants and
     * what a reminder decides on.
     */
    public function state(): string
    {
        return match (true) {
            $this->status === 'failed' => 'failed',
            $this->status === 'pending' => 'pending',
            $this->hasExpired() => 'expired',
            $this->expiresWithin(30) => 'expiring',
            default => 'valid',
        };
    }

    public function scopeNeedsAttention(Builder $q): Builder
    {
        return $q->where(function (Builder $sub) {
            $sub->where('status', 'pending')
                ->orWhere('status', 'failed')
                ->orWhere(fn (Builder $e) => $e->whereNotNull('expires_on')
                    ->where('expires_on', '<=', now()->addDays(30)));
        });
    }
}
