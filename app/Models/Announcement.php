<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'body', 'is_pinned', 'is_active', 'created_by'];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Safe HTML for the body: escape everything, turn plain URLs into clickable
     * links, and keep line breaks. Lets admins paste text + links safely.
     */
    public function bodyHtml(): HtmlString
    {
        $text = e($this->body);
        $text = preg_replace(
            '~(https?://[^\s<]+)~i',
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-brand-600 dark:text-brand-400 underline break-words">$1</a>',
            $text
        );

        return new HtmlString(nl2br($text));
    }
}
