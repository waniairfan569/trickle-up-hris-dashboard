<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceImportLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'filename',
        'imported_by',
        'source',
        'imported',
        'skipped',
        'unmapped',
    ];

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
