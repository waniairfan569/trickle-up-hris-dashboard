<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\ReviewCycle;
use App\Models\PerformanceReview;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_employee_cannot_see_submitted_manager_review()
    {
        $manager = User::factory()->asManager()->create();
        $employee = User::factory()->asEmployee($manager->id)->create();
        
        $cycle = ReviewCycle::create([
            'name' => 'Q1', 
            'start_date' => now(), 
            'end_date' => now()->addMonth(), 
            'status' => 'active', 
            'created_by' => $manager->id
        ]);

        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'reviewee_id' => $employee->id,
            'reviewer_id' => $manager->id,
            'type' => 'manager',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($employee)->get("/performance/{$review->id}");
        $response->assertStatus(403);
    }

    public function test_employee_can_see_shared_review()
    {
        $manager = User::factory()->asManager()->create();
        $employee = User::factory()->asEmployee($manager->id)->create();
        
        $cycle = ReviewCycle::create([
            'name' => 'Q1', 
            'start_date' => now(), 
            'end_date' => now()->addMonth(), 
            'status' => 'active', 
            'created_by' => $manager->id
        ]);

        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'reviewee_id' => $employee->id,
            'reviewer_id' => $manager->id,
            'type' => 'manager',
            'status' => 'shared',
        ]);

        $response = $this->actingAs($employee)->get("/performance/{$review->id}");
        $response->assertStatus(200);
    }

    public function test_manager_can_write_review_for_direct_report()
    {
        $manager = User::factory()->asManager()->create();
        $employee = User::factory()->asEmployee($manager->id)->create();
        
        $cycle = ReviewCycle::create([
            'name' => 'Q1', 
            'start_date' => now(), 
            'end_date' => now()->addMonth(), 
            'status' => 'active', 
            'created_by' => $manager->id
        ]);

        $response = $this->actingAs($manager)->post("/performance/manager-review/{$employee->id}", [
            'cycle_id' => $cycle->id,
            'action' => 'save',
            'feedback' => 'Good work'
        ]);

        $response->assertStatus(302); // redirect
        $this->assertDatabaseHas('performance_reviews', [
            'reviewee_id' => $employee->id,
            'reviewer_id' => $manager->id,
            'type' => 'manager',
        ]);
    }

    public function test_only_super_admin_can_reopen_review()
    {
        $admin = User::factory()->asAdmin()->create();
        $manager = User::factory()->asManager()->create();
        $employee = User::factory()->asEmployee($manager->id)->create();
        
        $cycle = ReviewCycle::create([
            'name' => 'Q1', 
            'start_date' => now(), 
            'end_date' => now()->addMonth(), 
            'status' => 'active', 
            'created_by' => $admin->id
        ]);

        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'reviewee_id' => $employee->id,
            'reviewer_id' => $manager->id,
            'type' => 'manager',
            'status' => 'signed',
        ]);

        // Manager tries to reopen
        $response = $this->actingAs($manager)->post("/performance/{$review->id}/reopen");
        $response->assertStatus(403);

        // Admin tries to reopen
        $response = $this->actingAs($admin)->post("/performance/{$review->id}/reopen");
        $response->assertRedirect();
        
        $this->assertDatabaseHas('performance_reviews', [
            'id' => $review->id,
            'status' => 'draft',
            'reopened_by' => $admin->id
        ]);
    }
}
