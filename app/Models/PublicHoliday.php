<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'company_id','name','date','year',
        'country_code','type','is_optional',
    ];
    protected $casts = ['date' => 'date', 'is_optional' => 'boolean'];
}
