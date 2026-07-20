<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobLocation extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_entity_id',
        'name',
        'city',
        'country',
        'country_name',
        'timezone',
        'is_remote',
        'is_active',
        'employee_count',
    ];

    protected $casts = [
        'is_remote' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['display_name', 'flag'];

    /** ISO country code => display name (the set HR can choose from). */
    public const COUNTRIES = [
        'PK' => 'Pakistan',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'AE' => 'UAE',
        'SA' => 'Saudi Arabia',
        'IN' => 'India',
        'CA' => 'Canada',
        'AU' => 'Australia',
    ];

    /** ISO country code => flag emoji. */
    public const FLAGS = [
        'PK' => '🇵🇰',
        'GB' => '🇬🇧',
        'US' => '🇺🇸',
        'AE' => '🇦🇪',
        'SA' => '🇸🇦',
        'IN' => '🇮🇳',
        'CA' => '🇨🇦',
        'AU' => '🇦🇺',
    ];

    protected static function booted(): void
    {
        // Keep the cached employee_count in sync when the location is saved.
        // updateQuietly avoids re-triggering the saved event (no recursion).
        static::saved(function (JobLocation $location) {
            $count = $location->employees()->count();
            if ((int) $location->employee_count !== (int) $count) {
                $location->updateQuietly(['employee_count' => $count]);
            }
        });
    }

    // Relationships
    public function employees()
    {
        return $this->hasMany(User::class, 'job_location_id');
    }

    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRemote($query)
    {
        return $query->where('is_remote', true);
    }

    public function scopeOnsite($query)
    {
        return $query->where('is_remote', false);
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_remote) {
            return "🏠 {$this->name}";
        }

        return "🏢 {$this->name}" . ($this->city ? " — {$this->city}" : '');
    }

    public function getFlagAttribute(): string
    {
        return self::FLAGS[$this->country] ?? '🌐';
    }

    /** Recompute and persist the cached employee count. */
    public function refreshEmployeeCount(): void
    {
        $this->updateQuietly(['employee_count' => $this->employees()->count()]);
    }
}
