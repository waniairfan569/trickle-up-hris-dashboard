<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    protected $fillable = [
        'company_id','name','date','year',
        'country_code','type','is_optional',
    ];
    protected $casts = ['date' => 'date', 'is_optional' => 'boolean'];
}
