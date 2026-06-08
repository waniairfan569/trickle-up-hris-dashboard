<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;
    
    protected $guarded = [];
    protected $casts = [
        'data' => 'json',
        'read_at' => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function scopeUnread($query) { return $query->whereNull('read_at'); }
}
