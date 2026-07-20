<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subdomain', 'status', 'plan',
        'brand_name', 'logo_url', 'primary_color', 'from_email', 'timezone', 'currency',
        'trial_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** The default tenant that owns all pre-SaaS data. */
    public static function default(): ?self
    {
        return static::where('slug', 'trickle-up')->first();
    }

    /** Brand name shown in the UI / emails (falls back to the org name). */
    public function displayName(): string
    {
        return $this->brand_name ?: $this->name;
    }
}
