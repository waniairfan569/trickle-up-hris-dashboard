<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A screen or poster people scan to clock in.
 *
 * The code it shows is derived from a secret and the clock, so it changes
 * every few seconds. That is the whole security model: a photograph of the
 * code is worthless by the time somebody gets home with it.
 */
class QrClockPoint extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'office_location_id', 'secret',
        'rotate_seconds', 'is_active', 'last_used_at', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rotate_seconds' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function officeLocation()
    {
        return $this->belongsTo(OfficeLocation::class, 'office_location_id');
    }

    public static function newSecret(): string
    {
        return Str::random(64);
    }

    /** How long a code is good for, kept inside sane bounds. */
    public function window(): int
    {
        return max(10, min(300, (int) ($this->rotate_seconds ?: 30)));
    }

    /**
     * The code to show right now.
     *
     * Derived rather than stored, so nothing has to be written every few
     * seconds and two servers behind a load balancer agree without talking.
     */
    public function currentCode(?int $at = null): string
    {
        return $this->codeFor($this->slotFor($at ?? time()));
    }

    /**
     * Is this the code we are showing, or the one just before it?
     *
     * The one before is accepted because somebody can scan in the last moment
     * of a window and arrive a second into the next one, and being told their
     * valid scan failed is worse than the extra few seconds of exposure.
     */
    public function accepts(string $code): bool
    {
        $slot = $this->slotFor(time());

        foreach ([$slot, $slot - 1] as $candidate) {
            if (hash_equals($this->codeFor($candidate), $code)) {
                return true;
            }
        }

        return false;
    }

    private function slotFor(int $timestamp): int
    {
        return intdiv($timestamp, $this->window());
    }

    private function codeFor(int $slot): string
    {
        return substr(hash_hmac('sha256', $this->id . '|' . $slot, (string) $this->secret), 0, 16);
    }
}
