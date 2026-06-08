<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id','company_id','leave_year',
        'annual_total','annual_used','annual_pending',
        'sick_total','sick_used',
        'unpaid_used',
        'birthday_total','birthday_used',
        'parental_total','parental_used',
        'comp_off_earned','comp_off_used','comp_off_expires',
    ];

    protected $casts = [
        'comp_off_expires' => 'date',
        'annual_total'  => 'float', 'annual_used'   => 'float', 'annual_pending' => 'float',
        'sick_total'    => 'float', 'sick_used'     => 'float',
        'unpaid_used'   => 'float',
        'birthday_total'=> 'float', 'birthday_used' => 'float',
        'parental_total'=> 'float', 'parental_used' => 'float',
        'comp_off_earned'=> 'float','comp_off_used' => 'float',
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function getAnnualRemainingAttribute() {
        return max(0, $this->annual_total - $this->annual_used - $this->annual_pending);
    }
    public function getSickRemainingAttribute() {
        return max(0, $this->sick_total - $this->sick_used);
    }
    public function getBirthdayRemainingAttribute() {
        return max(0, $this->birthday_total - $this->birthday_used);
    }
    public function getParentalRemainingAttribute() {
        return max(0, $this->parental_total - $this->parental_used);
    }
    public function getCompOffRemainingAttribute() {
        return max(0, $this->comp_off_earned - $this->comp_off_used);
    }
    public function getTotalRemainingAttribute() {
        return $this->annual_remaining + $this->sick_remaining
            + $this->birthday_remaining + $this->comp_off_remaining;
    }
}
