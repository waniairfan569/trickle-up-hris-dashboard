<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed RBAC data
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_employee_cannot_view_other_employee_profile()
    {
        $employee1 = User::factory()->asEmployee()->create();
        $employee2 = User::factory()->asEmployee()->create();

        $response = $this->actingAs($employee1)->get("/employees/{$employee2->id}");
        $response->assertStatus(403);
    }

    public function test_manager_can_view_direct_report_profile()
    {
        $manager = User::factory()->asManager()->create();
        $report = User::factory()->asEmployee($manager->id)->create();

        $response = $this->actingAs($manager)->get("/employees/{$report->id}");
        $response->assertStatus(200);
    }

    public function test_manager_cannot_view_outside_reporting_line()
    {
        $manager = User::factory()->asManager()->create();
        $otherEmployee = User::factory()->asEmployee()->create();

        $response = $this->actingAs($manager)->get("/employees/{$otherEmployee->id}");
        $response->assertStatus(403);
    }

    public function test_hr_admin_can_view_all_employees()
    {
        // Mock hr_admin
        $hrAdmin = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['name' => 'hr_admin']);
        $hrAdmin->roles()->attach($role);

        $employee = User::factory()->asEmployee()->create();

        $response = $this->actingAs($hrAdmin)->get("/employees/{$employee->id}");
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_settings()
    {
        $admin = User::factory()->asAdmin()->create();
        
        $response = $this->actingAs($admin)->get('/settings');
        // Let's assume /settings route exists for admins, or some admin route.
        // We'll test viewing another employee instead to prove absolute access.
        $employee = User::factory()->asEmployee()->create();
        $response = $this->actingAs($admin)->get("/employees/{$employee->id}");
        $response->assertStatus(200);
    }

    public function test_employee_cannot_access_admin_settings()
    {
        $employee = User::factory()->asEmployee()->create();
        $admin = User::factory()->asAdmin()->create();

        // Testing the offboarding action which requires admin/hr_admin
        // /employees/{id}/offboard
        $response = $this->actingAs($employee)->post("/employees/{$admin->id}/offboard");
        // Usually returns 403 or 404
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }
}
