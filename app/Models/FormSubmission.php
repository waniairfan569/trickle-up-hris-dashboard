<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id', 'user_id', 'assignment_id', 'status',
        'started_at', 'submitted_at', 'signature_data', 'ip_address',
        'review_status', 'review_note', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Tailwind classes for the review-status badge. */
    public function reviewBadgeClass(): string
    {
        return match ($this->review_status) {
            'approved' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            'rejected' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        };
    }

    public function reviewLabel(): string
    {
        return match ($this->review_status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Awaiting review',
        };
    }

    public function form()
    {
        return $this->belongsTo(CompanyForm::class, 'form_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responses()
    {
        return $this->hasMany(FormResponse::class, 'submission_id');
    }

    public function assignment()
    {
        return $this->belongsTo(FormAssignment::class, 'assignment_id');
    }

    public function isComplete(): bool
    {
        return $this->status === 'submitted';
    }

    public function progressPercent(): int
    {
        $form = $this->form()->with('fields')->first();
        if (!$form) {
            return 0;
        }
        $required = $form->fields->filter(fn ($f) => $f->isInputField() && $f->is_required);
        $total = $required->count();
        if ($total === 0) {
            // No required fields — base progress on any answered input fields.
            $total = $form->fields->filter(fn ($f) => $f->isInputField())->count() ?: 1;
        }
        $answeredKeys = $this->responses()->whereNotNull('value')->where('value', '!=', '')->pluck('field_key')->all();
        $filled = $required->count()
            ? $required->filter(fn ($f) => in_array($f->field_key, $answeredKeys, true))->count()
            : count($answeredKeys);

        return min(100, (int) round($filled / max(1, $total) * 100));
    }
}
