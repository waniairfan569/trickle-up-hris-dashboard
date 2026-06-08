<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Standard Office (Default)
        $standard = Shift::create([
            'name' => 'Standard Office',
            'start_time' => '09:30',
            'end_time' => '18:00',
            'break_minutes' => 30,
            'crosses_midnight' => false,
            'working_days' => ["Mon","Tue","Wed","Thu","Fri"],
            'color' => '#3B82F6', // Blue
            'is_default' => true,
            'auto_assign_to_new_employees' => true,
            'is_active' => true
        ]);

        // 2. Create Evening Shift
        Shift::create([
            'name' => 'Evening Shift',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'break_minutes' => 30,
            'crosses_midnight' => false,
            'working_days' => ["Mon","Tue","Wed","Thu","Fri"],
            'color' => '#8B5CF6', // Purple
            'is_default' => false,
            'auto_assign_to_new_employees' => false,
            'is_active' => true
        ]);

        // 3. Create Night Shift
        Shift::create([
            'name' => 'Night Shift',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_minutes' => 30,
            'crosses_midnight' => true,
            'working_days' => ["Mon","Tue","Wed","Thu","Fri"],
            'color' => '#64748B', // Slate/Gray
            'is_default' => false,
            'auto_assign_to_new_employees' => false,
            'is_active' => true
        ]);

        // 4. Assign default shift to all existing users without a shift
        $usersWithoutShift = User::whereNotIn('id', function($query) {
            $query->select('user_id')
                  ->from('shift_assignments')
                  ->where('assignment_type', 'recurring');
        })->where('status', 'active')->get();

        foreach ($usersWithoutShift as $user) {
            ShiftAssignment::create([
                'user_id' => $user->id,
                'shift_id' => $standard->id,
                'assignment_type' => 'recurring',
                'recurring_start_date' => now()->toDateString(),
                'recurring_days' => ["Mon","Tue","Wed","Thu","Fri"],
                'notes' => 'Default shift — assigned by seeder'
            ]);
        }
    }
}
