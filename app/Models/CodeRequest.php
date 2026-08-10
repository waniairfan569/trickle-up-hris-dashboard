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
        // Encrypted at rest; revealed only on explicit admin request (never rendered in bulk).
        'code_provided' => \App\Casts\TolerantEncrypted::class,
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

    /** Whether a code value is stored (checks the raw column, no decryption). */
    public function hasCode(): bool
    {
        return filled($this->getRawOriginal('code_provided'));
    }

    /** Classify the stored value for a UI label (Email / Login / OTP / Note / Code) — the value itself is never exposed by this. */
    public function valueType(): ?string
    {
        $v = trim((string) $this->code_provided);
        if ($v === '') {
            return null;
        }
        if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
            return 'Email';
        }
        if (str_contains($v, '@')) {
            return 'Login'; // email + password/token combo
        }
        if (preg_match('/^\d{4,8}$/', $v)) {
            return 'OTP';
        }
        if (str_contains($v, ' ') || strlen($v) > 40) {
            return 'Note';
        }
        return 'Code';
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
