<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
class UserDepartmentAssignment extends Model 
{ 
    use BelongsToTenant;
    protected $guarded = []; 

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}

