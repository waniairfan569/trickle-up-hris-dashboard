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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

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
