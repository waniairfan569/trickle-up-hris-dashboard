<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentView extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['document_id', 'user_id', 'action', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(CompanyDocument::class, 'document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
