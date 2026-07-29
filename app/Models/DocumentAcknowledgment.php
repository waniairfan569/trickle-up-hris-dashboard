<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DocumentAcknowledgment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'document_id', 'user_id', 'acknowledged_at', 'ip_address', 'field_values',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'field_values' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(CompanyDocument::class, 'document_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
