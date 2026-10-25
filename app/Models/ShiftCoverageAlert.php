<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A note that an uncovered shift has already been reported.
 *
 * Only here so the daily watcher says a thing once. The row is removed when
 * the hole closes, so a gap that reopens is reported again rather than being
 * mistaken for one already dealt with.
 */
class ShiftCoverageAlert extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'shift_id', 'date', 'notified_at'];

    protected $casts = [
        'date' => 'date',
        'notified_at' => 'datetime',
    ];

    /** The identity of a gap: one person, one shift, one day. */
    public static function keyFor(int $userId, int $shiftId, string $date): string
    {
        return $userId . '|' . $shiftId . '|' . $date;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
