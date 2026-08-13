<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAudience extends Model
{
    protected $fillable = [
        'event_id',
        'audience_type',
        'audience_id',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
