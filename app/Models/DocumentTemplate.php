<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'tags',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'version',
        'status',
    ];

    protected $casts = [
        'tags' => 'array',
        'file_size' => 'integer',
        'version' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entities()
    {
        return $this->belongsToMany(CompanyEntity::class, 'document_template_entity');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'document_template_department');
    }

    public function signers()
    {
        return $this->hasMany(DocumentTemplateSigner::class)->orderBy('position');
    }

    public function fields()
    {
        return $this->hasMany(DocumentTemplateField::class);
    }

    /** Human-readable file size, e.g. "1.4 MB". */
    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }

    /** Is this template a PDF (eligible for the Phase-2 placement editor)? */
    public function isPdf(): bool
    {
        return str_contains((string) $this->file_mime, 'pdf')
            || str_ends_with(strtolower((string) $this->file_name), '.pdf');
    }
}
