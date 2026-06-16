<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateField extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_template_id',
        'profile_field_id',
        'field_key',
        'label',
        'section',
        'field_type',
        'assignee',
        'page',
        'pos_x',
        'pos_y',
        'width',
        'height',
    ];

    protected $casts = [
        'page' => 'integer',
        'pos_x' => 'float',
        'pos_y' => 'float',
        'width' => 'float',
        'height' => 'float',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function profileField()
    {
        return $this->belongsTo(ProfileField::class, 'profile_field_id');
    }
}
