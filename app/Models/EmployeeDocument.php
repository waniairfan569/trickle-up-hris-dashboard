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
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** A linked signed e-signature document (not an uploaded file). */
    public function isLinked(): bool
    {
        return $this->source_type !== null;
    }

    /** Where "view/download" points for a linked entry (the completed document). */
    public function linkUrl(): ?string
    {
        if ($this->source_type === 'signature' && $this->source_id) {
            return route('documents.show', $this->source_id);
        }

        return null;
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
