<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A record that we chased somebody about something unacknowledged, so the
 * nightly job does not chase the same person about the same thing every day.
 *
 * Kept separately from the acknowledgement tables because a company document
 * has no row for a person until they acknowledge — see the migration.
 */
class AcknowledgementReminder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'acknowledgeable_type', 'acknowledgeable_id',
        'user_id', 'sent_at', 'times_sent',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'times_sent' => 'integer',
    ];

    public function acknowledgeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
