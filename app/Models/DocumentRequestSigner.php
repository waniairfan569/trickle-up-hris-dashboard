<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequestSigner extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'document_request_id',
        'position',
        'user_id',
        'source_role',
        'status',
        'signature_image',
        'signed_at',
        'signed_ip',
    ];

    protected $casts = [
        'position' => 'integer',
        'signed_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->source_role) {
            'employee' => 'Employee',
            'line_manager' => 'Line manager',
            'hr_admin' => 'HR admin',
            'specific' => 'Named signer',
            default => 'Signer',
        };
    }
}
