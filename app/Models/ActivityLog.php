<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    
    protected $guarded = [];
    protected $casts = ['metadata' => 'json'];

    public function company() { return $this->belongsTo(Company::class); }
    public function user() { return $this->belongsTo(User::class); }
}
