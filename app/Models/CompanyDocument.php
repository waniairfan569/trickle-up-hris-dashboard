<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyDocument extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_entity_id', 'category_id', 'title', 'description', 'file_path', 'file_name',
        'file_size', 'file_type', 'file_extension', 'version', 'version_notes', 'access_level',
        'is_active', 'requires_acknowledgment', 'requires_signature', 'template_id',
        'download_count', 'view_count', 'uploaded_by', 'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_acknowledgment' => 'boolean',
        'requires_signature' => 'boolean',
        'expires_at' => 'date',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'view_count' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    /** The signing template driving send-for-signature (when requires_signature). */
    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    /** This document can be sent for e-signature once a template is set up. */
    /**
     * Signing status for the admin list: null when the document isn't a signing
     * document, else ['label','color','request'] from its latest sent request.
     */
    public function signingStatus(): ?array
    {
        if (!$this->requires_signature) {
            return null;
        }

        $template = $this->template;
        if (!$template) {
            return ['label' => 'Draft', 'color' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300', 'request' => null];
        }

        $req = $template->relationLoaded('requests')
            ? $template->requests->sortByDesc('id')->first()
            : $template->requests()->with('signers')->latest('id')->first();

        if (!$req) {
            return ['label' => 'Draft', 'color' => 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300', 'request' => null];
        }
        if ($req->status === 'completed') {
            return ['label' => 'Signed', 'color' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400', 'request' => $req];
        }
        if ($req->status === 'declined') {
            return ['label' => 'Declined', 'color' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400', 'request' => $req];
        }

        $total = $req->signers->count();
        $signed = $req->signers->where('status', 'signed')->count();

        return ['label' => "Awaiting signature ({$signed}/{$total})", 'color' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400', 'request' => $req];
    }

    public function isSignable(): bool
    {
        return $this->requires_signature && $this->file_extension === 'pdf';
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function accessRecords()
    {
        return $this->hasMany(DocumentAccess::class, 'document_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(DocumentAcknowledgment::class, 'document_id');
    }

    public function acknowledgmentFor(?User $user): ?DocumentAcknowledgment
    {
        if (!$user) {
            return null;
        }

        return $this->acknowledgments()->where('user_id', $user->id)->first();
    }

    public function isAcknowledgedBy(?User $user): bool
    {
        return $user && $this->acknowledgments()->where('user_id', $user->id)->exists();
    }

    /** Non-deactivated users who can access this document (its acknowledgment audience). */
    public function eligibleUsers()
    {
        $base = User::where('account_status', '!=', 'deactivated');

        return match ($this->access_level) {
            'company_wide' => $base->get(),
            'department' => $base->whereIn('department_id',
                $this->accessRecords()->where('access_type', 'department')->pluck('access_id'))->get(),
            'specific_users' => $base->whereIn('id',
                $this->accessRecords()->where('access_type', 'user')->pluck('access_id'))->get(),
            default => collect(),
        };
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
