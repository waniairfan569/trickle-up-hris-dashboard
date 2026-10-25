<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line in the history of a booking: who did what to it, and when.
 *
 * Append-only, and without an `updated_at`, for the same reason
 * probation_events is: a record of a conversation being rearranged is only
 * worth anything if it cannot be tidied up afterwards.
 */
class OneToOneEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['one_to_one_id', 'action', 'new_time', 'note', 'acted_by', 'created_at'];

    protected $casts = [
        'new_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function oneToOne()
    {
        return $this->belongsTo(OneToOne::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function label(): string
    {
        return match ($this->action) {
            'proposed' => 'Proposed',
            'confirmed' => 'Agreed',
            'declined' => 'Declined',
            'rescheduled' => 'Another time proposed',
            'held' => 'Marked as held',
            'cancelled' => 'Cancelled',
            'missed' => 'Marked as not happened',
            'note' => 'Note added',
            default => ucfirst((string) $this->action),
        };
    }
}
