<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-only conduct / behaviour note logged against an employee. Shows only
 * on the admin side (employee profile → Time tracking) and in their report.
 */
class ConductNote extends Model
{
    use BelongsToTenant;

    /** Suggested categories for the log (free text is still allowed). */
    public const CATEGORIES = ['Behaviour', 'Punctuality', 'Performance', 'Policy', 'Attitude', 'Positive', 'Other'];

    protected $fillable = ['user_id', 'author_id', 'occurred_on', 'category', 'note'];

    protected $casts = ['occurred_on' => 'date'];

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
