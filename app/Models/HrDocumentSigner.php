<?php

namespace App\Models;

use App\Models\Concerns\IsDocumentSigner;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One assigned signer on a sent HR document (the subject employee or their
 * manager) and the signature field(s) they are responsible for.
 */
class HrDocumentSigner extends Model
{
    use BelongsToTenant;
    use IsDocumentSigner;

    protected $fillable = [
        'hr_document_id', 'user_id', 'role', 'sign_order', 'field_ids', 'signed_at', 'signed_ip',
    ];

    protected $casts = [
        'field_ids'  => 'array',
        'signed_at'  => 'datetime',
        'sign_order' => 'integer',
    ];

    // "Whose turn is it" lives in IsDocumentSigner, shared with the e-signature
    // side, so the rule is written once rather than once per table.

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
