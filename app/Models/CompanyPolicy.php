<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class CompanyPolicy extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $table = 'company_policies';

    protected $fillable = [
        'company_entity_id', 'title', 'description', 'content', 'version', 'category', 'status',
        'requires_acknowledgment', 'requires_signature', 'document_file', 'document_filename',
        'document_size', 'effective_date', 'review_date', 'acknowledgment_deadline', 'created_by',
    ];

    protected $casts = [
        'requires_acknowledgment' => 'boolean',
        'requires_signature' => 'boolean',
        'effective_date' => 'date',
        'review_date' => 'date',
        'document_size' => 'integer',
    ];

    public function assignments()
    {
        return $this->hasMany(PolicyAssignment::class, 'policy_id');
    }

    public function acknowledgments()
    {
        return $this->hasMany(PolicyAcknowledgment::class, 'policy_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $q): Builder { return $q->where('status', 'active'); }
    public function scopeRequiresAcknowledgment(Builder $q): Builder { return $q->where('requires_acknowledgment', true); }

    public function getAcknowledgmentRateAttribute(): int
    {
        $total = $this->acknowledgments()->count();
        if ($total === 0) {
            return 0;
        }

        return (int) round($this->acknowledgments()->where('status', 'acknowledged')->count() / $total * 100);
    }

    public function getDocumentSizeLabelAttribute(): string
    {
        $bytes = (int) $this->document_size;
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = min((int) floor(log($bytes, 1024)), 3);

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    public function getCategoryLabelAttribute(): string
    {
        return [
            'hr' => 'HR', 'it' => 'IT', 'finance' => 'Finance', 'legal' => 'Legal',
            'health_safety' => 'Health & Safety', 'general' => 'General',
        ][$this->category] ?? ucfirst((string) $this->category);
    }

    public function isAcknowledgedBy(User $user): bool
    {
        return $this->acknowledgments()->where('user_id', $user->id)->where('status', 'acknowledged')->exists();
    }

    public function hasViewedBy(User $user): bool
    {
        return $this->acknowledgments()->where('user_id', $user->id)->whereIn('status', ['viewed', 'acknowledged'])->exists();
    }

    /** All users this policy is assigned to (department/all expanded). */
    public function getAssignedUsers(): Collection
    {
        $userIds = collect();
        $allCompany = false;

        foreach ($this->assignments()->get() as $a) {
            if ($a->assigned_to_type === 'all') {
                $allCompany = true;
            } elseif ($a->assigned_to_type === 'user' && $a->assigned_to_id) {
                $userIds->push($a->assigned_to_id);
            } elseif ($a->assigned_to_type === 'department' && $a->assigned_to_id) {
                $userIds = $userIds->merge(User::where('department_id', $a->assigned_to_id)->where('account_status', '!=', 'deactivated')->pluck('id'));
            }
        }

        if ($allCompany) {
            return User::where('account_status', 'active')->get();
        }

        return User::whereIn('id', $userIds->unique()->all())->get();
    }
}
