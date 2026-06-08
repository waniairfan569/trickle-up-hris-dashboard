<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserDepartmentAssignment extends Model 
{ 
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

