<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmployeeFieldValue extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'field_id',
        'value',
        'updated_by',
    ];

    public function field()
    {
        return $this->belongsTo(ProfileField::class, 'field_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getDisplayValue()
    {
        if (is_null($this->value)) {
            return null;
        }

        if ($this->field->is_encrypted) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception $e) {
                return null;
            }
        }

        if (in_array($this->field->type, ['multi_select', 'date_range'])) {
            return json_decode($this->value, true) ?? [];
        }

        return $this->value;
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            // Load field if not already loaded to check encryption status
            $field = $model->field()->first();
            
            if ($field && $field->is_encrypted && !is_null($model->value)) {
                // Ensure we don't double-encrypt. A simple way is to attempt decrypt; if it fails, encrypt.
                // However, the cleanest way is checking if the value was dirty or just explicitly encrypting 
                // raw incoming inputs.
                if ($model->isDirty('value')) {
                    $model->value = Crypt::encryptString($model->value);
                }
            }
        });
    }
}
