<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequestEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'document_request_id',
        'user_id',
        'action',
        'detail',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
