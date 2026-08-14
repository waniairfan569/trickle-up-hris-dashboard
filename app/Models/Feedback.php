<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use BelongsToTenant;

    protected $table = 'feedback';

    protected $fillable = [
        'company_id',
        'user_id',
        'category',
        'subject',
        'message',
        'status',
        'admin_response',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'feedback' => 'General feedback',
        'issue' => 'Issue / bug',
        'suggestion' => 'Suggestion',
        'complaint' => 'Complaint',
        'other' => 'Other',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'resolved');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    /** [label, tailwind classes] for the status badge. */
    public function statusBadge(): array
    {
        return match ($this->status) {
            'resolved' => ['Resolved', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'],
            'in_progress' => ['In progress', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'],
            default => ['Open', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400'],
        };
    }
}
