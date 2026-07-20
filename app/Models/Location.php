<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
