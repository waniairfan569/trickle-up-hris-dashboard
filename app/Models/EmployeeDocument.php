<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'original_name',
        'path',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->size;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
