<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One assigned signer on a sent HR document (the subject employee or their
 * manager) and the signature field(s) they are responsible for.
 */
class HrDocumentSigner extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'hr_document_id', 'user_id', 'role', 'sign_order', 'field_ids', 'signed_at', 'signed_ip',
    ];

    protected $casts = [
        'field_ids'  => 'array',
        'signed_at'  => 'datetime',
        'sign_order' => 'integer',
    ];

    /**
     * Someone this document is still waiting on before this person's turn.
     * Null when it is their turn, or when the document has no signing order —
     * position 0 means "any time", which is how every document behaved before
     * an order could be set.
     */
    public function blockedBy(): ?self
    {
        if (! $this->sign_order) {
            return null;
        }

        return static::where('hr_document_id', $this->hr_document_id)
            ->whereNull('signed_at')
            ->where('sign_order', '>', 0)
            ->where('sign_order', '<', $this->sign_order)
            ->orderBy('sign_order')
            ->with('user')
            ->first();
    }

    public function document()
    {
        return $this->belongsTo(HrDocument::class, 'hr_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopePending($query)
    {
        return $query->whereNull('signed_at');
    }
}
