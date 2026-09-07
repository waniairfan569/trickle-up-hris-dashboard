<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single feature granted directly to one employee (feature_user table).
 * Custom-role feature grants live in role_feature (see Role::featureKeys()).
 */
class FeatureGrant extends Model
{
    protected $table = 'feature_user';

    protected $fillable = ['user_id', 'feature_key', 'granted_by'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
