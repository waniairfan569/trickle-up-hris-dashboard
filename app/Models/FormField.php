<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'form_id', 'label', 'field_key', 'type', 'placeholder', 'options',
        'is_required', 'validation_rules', 'help_text', 'sort_order', 'width',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const LAYOUT_TYPES = ['heading', 'paragraph', 'divider'];

    public function form()
    {
        return $this->belongsTo(CompanyForm::class, 'form_id');
    }

    /** True for fields that capture employee input (not layout-only elements). */
    public function isInputField(): bool
    {
        return !in_array($this->type, self::LAYOUT_TYPES, true);
    }
}
