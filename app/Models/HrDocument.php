<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A filled, on-file copy of an HR document template for one employee.
 */
class HrDocument extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'hr_document_template_id', 'user_id', 'template_name', 'title',
        'schema', 'data', 'period_start', 'period_end', 'status', 'created_by',
    ];

    protected $casts = [
        'schema'       => 'array',
        'data'         => 'array',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function template()
    {
        return $this->belongsTo(HrDocumentTemplate::class, 'hr_document_template_id');
    }

    /** The employee this document is about. */
    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** True once at least one signature field has been signed. */
    public function getIsSignedAttribute(): bool
    {
        foreach ((array) $this->data as $value) {
            if (is_array($value) && ! empty($value['image'])) {
                return true;
            }
        }

        return false;
    }
}
