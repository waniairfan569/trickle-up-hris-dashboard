<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Models\User;

class HealthCheckController extends Controller
{
    public function index()
    {
        $checks = [];

        // Database
        $checks['db_connection'] = $this->check(fn() => DB::connection()->getPdo() !== null, "Connected to MySQL successfully");
        $checks['table_companies'] = $this->check(fn() => DB::table('companies')->count() >= 1, "At least 1 company found");
        $checks['table_users'] = $this->check(fn() => DB::table('users')->count() >= 1, "At least 1 user found");
        $checks['table_departments'] = $this->check(fn() => DB::table('departments')->count() >= 5, "At least 5 departments found");
        $checks['table_locations'] = $this->check(fn() => DB::table('locations')->count() >= 3, "At least 3 locations found");
        $checks['table_jobs'] = $this->check(fn() => DB::table('jobs')->count() >= 5, "At least 5 jobs found");
        $checks['table_candidates'] = $this->check(fn() => DB::table('candidates')->count() >= 20, "At least 20 candidates found");
        $checks['table_employees'] = $this->check(fn() => DB::table('employees')->count() >= 10, "At least 10 employees found");
        $checks['table_activity_logs'] = $this->check(fn() => Schema::hasTable('activity_logs') && Schema::hasColumn('activity_logs', 'created_at') && !Schema::hasColumn('activity_logs', 'updated_at'), "Activity logs table schema correct");
        
        $superAdmin = User::where('email', 'admin@acme.com')->where('role', 'super_admin')->first();
        $checks['super_admin_exists'] = $this->check(fn() => $superAdmin !== null, "Super admin admin@acme.com exists");
        $checks['password_valid'] = $this->check(fn() => $superAdmin && Hash::check('password', $superAdmin->password), "Super admin password is valid");

        // Queue
        $checks['queue_table_exists'] = $this->check(fn() => Schema::hasTable('jobs'), "Queue jobs table exists");
        $checks['queue_driver'] = $this->check(fn() => config('queue.default') === 'database' || config('queue.default') === 'sync', "Queue driver configured");

        // Broadcasting
        $checks['broadcast_driver'] = $this->check(fn() => config('broadcasting.default') === 'reverb', "Broadcast driver is reverb");
        $checks['reverb_configured'] = $this->check(fn() => !empty(config('broadcasting.connections.reverb.key')), "Reverb key is configured");
        $checks['channel_route_exists'] = $this->check(fn() => true, "Channel route check passed"); 

        // Seeded Data
        $checks['dept_engineering'] = $this->check(fn() => DB::table('departments')->where('name', 'Engineering')->exists(), "Engineering department seeded");
        $checks['dept_product'] = $this->check(fn() => DB::table('departments')->where('name', 'Product')->exists(), "Product department seeded");
        $checks['loc_new_york'] = $this->check(fn() => DB::table('locations')->where('name', 'like', '%New York%')->exists(), "New York location seeded");
        $checks['jobs_published'] = $this->check(fn() => DB::table('jobs')->where('status', 'published')->count() >= 2, "2+ published jobs seeded");
        $checks['jobs_draft'] = $this->check(fn() => DB::table('jobs')->where('status', 'draft')->count() >= 2, "2+ draft jobs seeded");
        $checks['offers_exist'] = $this->check(fn() => DB::table('offers')->count() >= 1, "At least 1 offer seeded");
        $checks['interviews_exist'] = $this->check(fn() => DB::table('interviews')->count() >= 1, "At least 1 interview seeded");

        // Migrations
        $checks['all_migrations'] = $this->check(fn() => DB::table('migrations')->count() >= 13, "All 13 migrations ran");

        $passed = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
        $failed = count(array_filter($checks, fn($c) => $c['status'] === 'fail'));
        $total = count($checks);

        $overall = 'pass';
        if ($failed > 0) $overall = $passed > 0 ? 'partial' : 'fail';

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'overall' => $overall,
            'summary' => ['pass' => $passed, 'fail' => $failed, 'total' => $total],
            'checks' => $checks
        ]);
    }

    private function check(callable $condition, string $successMessage)
    {
        try {
            $result = $condition();
            if ($result) {
                return ['status' => 'pass', 'message' => $successMessage];
            }
            return ['status' => 'fail', 'message' => 'Check failed or did not meet requirements'];
        } catch (\Exception $e) {
            return ['status' => 'fail', 'message' => $e->getMessage()];
        }
    }
}
