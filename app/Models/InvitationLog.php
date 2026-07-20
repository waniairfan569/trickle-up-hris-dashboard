<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class InvitationLog extends Model
{
    use BelongsToTenant;
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'performed_by',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
