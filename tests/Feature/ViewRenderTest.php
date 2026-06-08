<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ViewRenderTest extends TestCase
{
    public function test_views_render_successfully()
    {
        // Get Admin
        $admin = User::where('email', 'admin@company.com')->first();
        
        // Ensure Manager exists
        $mgrEmp = Employee::where('id', 2)->first();
        if ($mgrEmp && !$mgrEmp->user_id) {
            $manager = User::create(['company_id' => 1, 'first_name' => $mgrEmp->first_name, 'last_name' => $mgrEmp->last_name, 'email' => $mgrEmp->email, 'password' => bcrypt('password')]);
            $mgrEmp->update(['user_id' => $manager->id]);
            $manager->roles()->attach(Role::where('slug', 'manager')->first()->id);
        }
        $manager = User::whereHas('roles', fn($q) => $q->where('slug', 'manager'))->first();

        // Ensure Employee exists
        $empEmp = Employee::where('id', 3)->first();
        if ($empEmp && !$empEmp->user_id) {
            $employee = User::create(['company_id' => 1, 'first_name' => $empEmp->first_name, 'last_name' => $empEmp->last_name, 'email' => $empEmp->email, 'password' => bcrypt('password')]);
            $empEmp->update(['user_id' => $employee->id, 'manager_id' => $manager->id ?? null]);
            $employee->roles()->attach(Role::where('slug', 'employee')->first()->id);
        }
        $employee = User::whereHas('roles', fn($q) => $q->where('slug', 'employee'))->first();

        // 1. Admin checks
        $this->actingAs($admin);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/employees')->assertStatus(200);
        $this->get('/employees/' . $employee->id)->assertStatus(200);
        
        // 2. Manager checks
        $this->actingAs($manager);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/employees')->assertStatus(200);
        $this->get('/employees/' . $employee->id)->assertStatus(200);

        // 3. Employee checks
        $this->actingAs($employee);
        $this->get('/dashboard')->assertStatus(200);
        $this->get('/employees')->assertStatus(200); // Usually 403 or scoped
        $this->get('/employees/' . $employee->id)->assertStatus(200);
    }
}
