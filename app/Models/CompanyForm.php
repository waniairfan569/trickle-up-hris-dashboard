<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CompanyForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_entity_id', 'title', 'description', 'slug', 'status',
        'is_anonymous', 'allow_multiple_submissions', 'show_progress_bar',
        'requires_signature', 'deadline', 'created_by',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'allow_multiple_submissions' => 'boolean',
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

    public function getSubmissionFor(User $user): ?FormSubmission
    {
        return $this->submissions()->where('user_id', $user->id)->latest('id')->first();
    }

    public function getPendingCountFor(User $user): int
    {
        return $this->submissions()->where('user_id', $user->id)->where('status', '!=', 'submitted')->count();
    }

    public function isOverdue(): bool
    {
        return $this->deadline && $this->deadline->isPast();
    }
}
