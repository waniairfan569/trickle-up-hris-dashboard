<?php

namespace Database\Seeders;

use App\Models\LeaveYearSetting;
use App\Models\TimeOffPolicy;
use Illuminate\Database\Seeder;

/**
 * Default leave-year settings: annual-type policies get 10%-of-annual
 * encashment; sick-type policies get none. July–June year, pro-rata on.
 * Idempotent — run with: php artisan db:seed --class=LeaveYearSettingsSeeder
 */
class LeaveYearSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TimeOffPolicy::withoutGlobalScopes()->where('is_active', true)->get() as $policy) {
            if (LeaveYearSetting::withoutGlobalScopes()->where('policy_id', $policy->id)->exists()) {
                continue;
            }

            $isSickLike = $policy->type === 'sick' || stripos((string) $policy->name, 'sick') !== false;

            LeaveYearSetting::create([
                'tenant_id' => $policy->tenant_id,
                'company_entity_id' => $policy->company_entity_id,
                'policy_id' => $policy->id,
                'name' => $policy->name . ' — leave year',
                'year_start_month' => 7,
                'year_start_day' => 1,
                'encashment_enabled' => !$isSickLike,
                'encashment_type' => $isSickLike ? 'none' : 'percent_of_annual',
                'encashment_value' => $isSickLike ? 0 : 10,
                'working_days_per_month' => 26,
                'carry_forward_enabled' => false,
                'pro_rata_enabled' => true,
                'pro_rata_cutoff_day' => 15,
                'pro_rata_round_to' => 'half',
                'auto_renewal_enabled' => true,
                'is_active' => true,
            ]);
        }
    }
}
