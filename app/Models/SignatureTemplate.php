<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SignatureTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'image_data',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
