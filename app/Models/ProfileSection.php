<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'name',
        'slug',
        'icon',
        'sort_order',
    ];

    public function template()
    {
        return $this->belongsTo(ProfileTemplate::class, 'template_id');
    }

    public function fields()
    {
        return $this->hasMany(ProfileField::class, 'section_id')->orderBy('sort_order');
    }
}
