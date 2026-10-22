<?php

namespace App\Models;

use App\Models\Concerns\NeedsAcknowledgement;
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
    use NeedsAcknowledgement;

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

    /**
     * Kept for the views that read $policy->acknowledgment_rate. The working
     * out lives in NeedsAcknowledgement, shared with company documents, so it
     * is written once — this only preserves the old "0 when nobody has been
     * asked" answer where that accessor expects an int.
     */
    public function getAcknowledgmentRateAttribute(): int
    {
        return $this->acknowledgementRate() ?? 0;
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

    // isAcknowledgedBy() comes from NeedsAcknowledgement, shared with company
    // documents. Viewing is this side's own idea — a document has no such step.

    /** A policy has its own page to read and acknowledge on. */
    public function acknowledgementUrl(): string
    {
        return route('policies.view', $this->id);
    }

    /**
     * A policy is in front of people from its effective date — before that it
     * may be assigned but not yet in force, so chasing would be premature.
     */
    public function acknowledgementPublishedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->effective_date ?? $this->created_at;
    }

    /** When acknowledgement was due, where one was set. */
    public function acknowledgementDueAt(): ?\Illuminate\Support\Carbon
    {
        return $this->acknowledgment_deadline
            ? \Illuminate\Support\Carbon::parse($this->acknowledgment_deadline)
            : null;
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
