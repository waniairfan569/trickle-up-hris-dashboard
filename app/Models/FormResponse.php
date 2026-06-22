<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FormResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id', 'field_id', 'field_key', 'field_label', 'value',
    ];

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function field()
    {
        return $this->belongsTo(FormField::class, 'field_id');
    }

    /** Human-readable value: arrays → comma list, file paths → just the name. */
    public function getDisplayValue(): string
    {
        $value = $this->value;
        if ($value === null || $value === '') {
            return '—';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode(', ', $decoded);
        }

        if (str_starts_with($value, 'form-uploads/')) {
            return basename($value);
        }

        if (str_starts_with($value, 'data:image')) {
            return '[signature]';
        }

        return (string) $value;
    }

    public function isFile(): bool
    {
        return is_string($this->value) && str_starts_with($this->value, 'form-uploads/');
    }
}
