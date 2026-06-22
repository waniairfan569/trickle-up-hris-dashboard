<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_entity_id', 'category_id', 'title', 'description', 'file_path', 'file_name',
        'file_size', 'file_type', 'file_extension', 'version', 'version_notes', 'access_level',
        'is_active', 'requires_acknowledgment', 'download_count', 'view_count', 'uploaded_by', 'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'expires_at' => 'date',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'view_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function accessRecords()
    {
        return $this->hasMany(DocumentAccess::class, 'document_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeCompanyWide(Builder $q): Builder
    {
        return $q->where('access_level', 'company_wide');
    }

    public function scopeAccessibleBy(Builder $q, User $user): Builder
    {
        return $q->where(function (Builder $sub) use ($user) {
            $sub->where('access_level', 'company_wide')
                ->orWhere(function (Builder $d) use ($user) {
                    $d->where('access_level', 'department')
                        ->whereHas('accessRecords', fn ($a) => $a->where('access_type', 'department')->where('access_id', $user->department_id));
                })
                ->orWhere(function (Builder $u) use ($user) {
                    $u->where('access_level', 'specific_users')
                        ->whereHas('accessRecords', fn ($a) => $a->where('access_type', 'user')->where('access_id', $user->id));
                });
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return match ($this->access_level) {
            'company_wide' => true,
            'department' => $this->accessRecords()->where('access_type', 'department')->where('access_id', $user->department_id)->exists(),
            'specific_users' => $this->accessRecords()->where('access_type', 'user')->where('access_id', $user->id)->exists(),
            default => false,
        };
    }

    public function logView(User $user, string $action = 'view'): void
    {
        DocumentView::create([
            'document_id' => $this->id,
            'user_id' => $user->id,
            'action' => $action,
            'created_at' => now(),
        ]);

        if ($action === 'download') {
            $this->increment('download_count');
        } elseif ($action === 'view') {
            $this->increment('view_count');
        }
    }

    public function getFileSizeLabelAttribute(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($bytes, 1024)), 3);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    /** Lucide icon + tailwind color classes keyed off the file extension. */
    public function getFileIconAttribute(): array
    {
        $ext = strtolower((string) $this->file_extension);

        return match (true) {
            $ext === 'pdf' => ['icon' => 'file-text', 'color' => 'text-rose-600 bg-rose-50 dark:bg-rose-500/10'],
            in_array($ext, ['doc', 'docx'], true) => ['icon' => 'file-text', 'color' => 'text-blue-600 bg-blue-50 dark:bg-blue-500/10'],
            in_array($ext, ['xls', 'xlsx', 'csv'], true) => ['icon' => 'file-spreadsheet', 'color' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10'],
            in_array($ext, ['ppt', 'pptx'], true) => ['icon' => 'presentation', 'color' => 'text-orange-600 bg-orange-50 dark:bg-orange-500/10'],
            in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true) => ['icon' => 'image', 'color' => 'text-violet-600 bg-violet-50 dark:bg-violet-500/10'],
            in_array($ext, ['zip', 'rar', '7z'], true) => ['icon' => 'file-archive', 'color' => 'text-amber-600 bg-amber-50 dark:bg-amber-500/10'],
            default => ['icon' => 'file', 'color' => 'text-slate-500 bg-slate-100 dark:bg-slate-700'],
        };
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
