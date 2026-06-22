<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAccess extends Model
{
    use HasFactory;

    protected $table = 'document_access';

    protected $fillable = ['document_id', 'access_type', 'access_id'];

    public function document()
    {
        return $this->belongsTo(CompanyDocument::class, 'document_id');
    }
}
