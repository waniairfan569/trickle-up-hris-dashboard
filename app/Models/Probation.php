<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Probation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'original_end_date',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'original_end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events()
    {
        return $this->hasMany(ProbationEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function logEvent(string $action, ?string $note = null, $newEnd = null): void
    {
        $this->events()->create([
            'action' => $action,
            'note' => $note,
            'new_end_date' => $newEnd,
            'reviewed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function getIsExtendedAttribute(): bool
    {
        return $this->original_end_date && $this->end_date->gt($this->original_end_date);
    }

    /** Whole days from today until the end date (negative = overdue). */
    public function getDaysRemainingAttribute(): int
    {
        return now()->startOfDay()->diffInDays($this->end_date, false);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'passed' => 'Confirmed',
            'failed' => 'Not confirmed',
            default => $this->end_date->lt(now()->startOfDay()) ? 'Review overdue' : 'On probation',
        };
    }
}
