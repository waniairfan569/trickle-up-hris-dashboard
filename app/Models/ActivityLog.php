<?php
namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use BelongsToTenant;
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    
    protected $guarded = [];
    protected $casts = ['metadata' => 'json', 'created_at' => 'datetime'];

    public function company() { return $this->belongsTo(Company::class); }
    public function user() { return $this->belongsTo(User::class); }
}
