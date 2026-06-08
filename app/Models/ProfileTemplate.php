<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfileTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sections()
    {
        return $this->hasMany(ProfileSection::class, 'template_id')->orderBy('sort_order');
    }

    public function employees()
    {
        return $this->belongsToMany(User::class, 'employee_profile_templates', 'template_id', 'user_id')
            ->withPivot('assigned_by', 'assigned_at');
    }

    public static function default()
    {
        return static::where('type', 'default')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
