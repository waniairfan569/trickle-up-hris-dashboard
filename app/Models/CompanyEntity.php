<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyEntity extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'registration_number',
        'logo',
        'address_line1',
        'address_line2',
        'city',
        'country',
        'timezone',
        'date_format',
        'time_format',
        'currency',
        'fiscal_year_start',
        'work_week_start',
        'working_days',
        'is_primary',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employees belonging to this company entity.
     */
    public function employees()
    {
        return $this->hasMany(User::class, 'company_entity_id');
    }

    /**
     * Get the departments belonging to this company entity.
     */
    public function departments()
    {
        return $this->hasMany(Department::class, 'company_entity_id');
    }

    /**
     * Return the primary entity.
     */
    public static function primary()
    {
        return static::where('is_primary', true)->first();
    }

    /**
     * Scope a query to only include active entities.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
