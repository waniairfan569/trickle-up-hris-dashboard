<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'color', 'description', 'sort_order'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (DocumentCategory $category) {
            if (empty($category->slug)) {
                $base = Str::slug($category->name);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $category->slug = $slug;
            }
        });
    }

    public function documents()
    {
        return $this->hasMany(CompanyDocument::class, 'category_id');
    }
}
