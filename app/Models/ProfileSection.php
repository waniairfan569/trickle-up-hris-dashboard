<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileSection extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'template_id',
        'name',
        'slug',
        'tab',
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
