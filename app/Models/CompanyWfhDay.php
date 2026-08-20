<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A date on which the whole company works from home (dashboard clock-in for all).
 */
class CompanyWfhDay extends Model
{
    use BelongsToTenant;

    protected $fillable = ['date', 'note', 'created_by'];

    protected $casts = ['date' => 'date'];

    /** True if the given date is a company-wide WFH day (tenant-scoped). */
    public static function isCompanyRemote(Carbon $date): bool
    {
        return static::where('date', $date->toDateString())->exists();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
