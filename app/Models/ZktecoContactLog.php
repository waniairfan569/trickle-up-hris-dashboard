<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single request that arrived at /iclock.
 *
 * Not tenant-scoped and not tied to a device on purpose: its whole job is to
 * record things we could not place, including requests carrying no serial.
 */
class ZktecoContactLog extends Model
{
    protected $table = 'zkteco_contact_log';

    public $timestamps = false;

    protected $fillable = [
        'method', 'path', 'query', 'serial', 'ip',
        'user_agent', 'body_bytes', 'handled', 'created_at',
    ];

    protected $casts = [
        'handled' => 'boolean',
        'created_at' => 'datetime',
    ];
}
