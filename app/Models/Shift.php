<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_entity_id',
        'name',
        'start_time',
        'end_time',
        'crosses_midnight',
        'break_minutes',
        'working_days',
        'color',
        'is_active',
        'is_default',
        'auto_assign_to_new_employees'
    ];

    protected $casts = [
        'crosses_midnight' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'auto_assign_to_new_employees' => 'boolean',
        'working_days' => 'array'
    ];

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->is_default) {
                // Set all other shifts to not default
                static::where('id', '!=', $model->id)->update(['is_default' => false]);
            }
            if ($model->auto_assign_to_new_employees) {
                // Set all other shifts to not auto assign
                static::where('id', '!=', $model->id)->update(['auto_assign_to_new_employees' => false]);
            }
        });
    }

    public static function getDefault(): ?Shift
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    public function assignments()
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
