<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateSigner extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'document_template_id',
        'position',
        'signer_type',
        'role',
        'employee_id',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** A friendly label for the signer (role name or employee name). */
    public function getDisplayNameAttribute(): string
    {
        if ($this->signer_type === 'employee' && $this->employee) {
            return $this->employee->full_name;
        }

        return match ($this->role) {
            'employee' => 'Employee',
            'line_manager' => 'Line manager',
            'hr_admin' => 'HR admin (requesting eSignature)',
            'me_now' => 'Me (now)',
            'sender' => 'Sender',
            default => 'Signer',
        };
    }
}
