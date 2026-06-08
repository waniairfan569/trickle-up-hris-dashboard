<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_entity_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'allow_remote',
        'created_by'
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
        'allow_remote' => 'boolean',
    ];

    public function employees()
    {
        return $this->belongsToMany(User::class, 'employee_office_locations')
                    ->withPivot('is_primary')
                    ->withTimestamps();
    }

    public function entity()
    {
        return $this->belongsTo(CompanyEntity::class, 'company_entity_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
