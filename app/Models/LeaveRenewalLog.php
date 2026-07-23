<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LeaveRenewalLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_entity_id', 'policy_id', 'leave_year_setting_id',
        'renewal_date', 'leave_year_label', 'triggered_by', 'triggered_by_user_id',
        'total_employees', 'employees_with_encashment', 'employees_no_encashment',
        'total_encashment_amount', 'total_days_lapsed',
        'status', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'renewal_date' => 'date',
        'total_employees' => 'integer',
        'employees_with_encashment' => 'integer',
        'employees_no_encashment' => 'integer',
        'total_encashment_amount' => 'decimal:2',
        'total_days_lapsed' => 'decimal:1',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function setting()
    {
        return $this->belongsTo(LeaveYearSetting::class, 'leave_year_setting_id');
    }

    public function policy()
    {
        return $this->belongsTo(TimeOffPolicy::class, 'policy_id');
    }

    public function triggeredByUser()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
