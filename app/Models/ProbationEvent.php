<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProbationEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'probation_id',
        'action',
        'new_end_date',
        'note',
        'reviewed_by',
        'created_at',
    ];

    protected $casts = [
        'new_end_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
