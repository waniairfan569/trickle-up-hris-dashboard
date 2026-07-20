<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use BelongsToTenant;
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'leave_unit' => 'string',
    ];

    public function departments()  { return $this->hasMany(Department::class); }
    public function locations()    { return $this->hasMany(Location::class); }
    public function users()        { return $this->hasMany(User::class); }
    public function employees()    { return $this->hasMany(Employee::class); }
    public function activityLogs() { return $this->hasMany(ActivityLog::class); }
}
