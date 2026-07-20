<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HolidayCalendar extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'company_entity_id',
        'name',
        'country_code',
        'year',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    public function holidays()
    {
        return $this->hasMany(Holiday::class, 'calendar_id')->orderBy('date');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'calendar_user', 'calendar_id', 'user_id')
                    ->withPivot('assigned_by')
                    ->withTimestamps();
    }
}
