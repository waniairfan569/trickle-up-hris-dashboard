<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CompanyForm extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_entity_id', 'title', 'description', 'slug', 'status', 'system_key',
        'is_anonymous', 'allow_multiple_submissions', 'is_monthly', 'show_progress_bar',
        'requires_signature', 'deadline', 'created_by',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'allow_multiple_submissions' => 'boolean',
        'is_monthly' => 'boolean',
        'show_progress_bar' => 'boolean',
        'requires_signature' => 'boolean',
        'deadline' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CompanyForm $form) {
            if (empty($form->slug)) {
                $base = Str::slug($form->title) ?: 'form';
                $slug = $base;
                $i = 1;
                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base . '-' . (++$i);
                }
                $form->slug = $slug;
            }
        });
    }

    public function fields()
    {
        return $this->hasMany(FormField::class, 'form_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assignments()
    {
        return $this->hasMany(FormAssignment::class, 'form_id');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Employees granted access to view responses + approve/reject/suggest. */
    public function reviewers()
    {
        return $this->belongsToMany(User::class, 'form_reviewers', 'form_id', 'user_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /** Admins can always review; other users only if assigned as a reviewer. */
    public function canBeReviewedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        return $this->reviewers()->where('users.id', $user->id)->exists();
    }

    public function scopeActive(Builder $q): Builder { return $q->where('status', 'active'); }
    public function scopeDraft(Builder $q): Builder { return $q->where('status', 'draft'); }
    public function scopeClosed(Builder $q): Builder { return $q->where('status', 'closed'); }

    /** The active form designated as this workspace's overtime form, if any. */
    public static function overtimeForm(): ?self
    {
        return static::where('system_key', 'overtime')->where('status', 'active')->first();
    }

    /** All users assigned to this form (department/all assignments expanded to users). */
    public function getAssignedUsersFor(): Collection
    {
        $userIds = collect();
        $allCompany = false;

        foreach ($this->assignments()->get() as $a) {
            if ($a->assigned_to_type === 'all') {
                $allCompany = true;
            } elseif ($a->assigned_to_type === 'user' && $a->assigned_to_id) {
                $userIds->push($a->assigned_to_id);
            } elseif ($a->assigned_to_type === 'department' && $a->assigned_to_id) {
                $userIds = $userIds->merge(
                    User::where('department_id', $a->assigned_to_id)->where('account_status', '!=', 'deactivated')->pluck('id')
                );
            }
        }

        if ($allCompany) {
            return User::where('account_status', 'active')->get();
        }

        return User::whereIn('id', $userIds->unique()->all())->get();
    }

    /** Current month key, e.g. "2026-07". */
    public function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    /** "2026-07" → "July 2026". */
    public static function periodLabel(?string $period): string
    {
        if (!$period) {
            return '';
        }
        try {
            return \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y');
        } catch (\Throwable $e) {
            return $period;
        }
    }

    /**
     * The user's submission for this form. For a monthly form it is scoped to a
     * period (defaults to the current month); for a one-off form the period is
     * ignored and the latest submission is returned (unchanged behaviour).
     */
    public function getSubmissionFor(User $user, ?string $period = null): ?FormSubmission
    {
        $query = $this->submissions()->where('user_id', $user->id);

        if ($this->is_monthly) {
            $query->where('period', $period ?? $this->currentPeriod());
        }

        return $query->latest('id')->first();
    }

    public function getPendingCountFor(User $user): int
    {
        return $this->submissions()->where('user_id', $user->id)->where('status', '!=', 'submitted')->count();
    }

    /** Distinct periods this form has submissions for, newest first (for filters). */
    public function submissionPeriods(): array
    {
        return $this->submissions()
            ->whereNotNull('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->all();
    }

    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast();
    }
}
