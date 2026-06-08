<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\TimeOffRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "Functional Testing Suite\n";
echo "========================\n";

try {
    // 1. Verify inline time-off approvals
    // Ensure Employee users exist
    $mgrEmp = \App\Models\Employee::where('id', 2)->first();
    if ($mgrEmp && !$mgrEmp->user_id) {
        $mgrUser = User::create(['company_id' => 1, 'first_name' => $mgrEmp->first_name, 'last_name' => $mgrEmp->last_name, 'email' => $mgrEmp->email, 'password' => bcrypt('password')]);
        $mgrEmp->update(['user_id' => $mgrUser->id]);
        $mgrUser->roles()->attach(\App\Models\Role::where('slug', 'manager')->first()->id);
    }
    
    $empEmp = \App\Models\Employee::where('id', 3)->first();
    if ($empEmp && !$empEmp->user_id) {
        $empUser = User::create(['company_id' => 1, 'first_name' => $empEmp->first_name, 'last_name' => $empEmp->last_name, 'email' => $empEmp->email, 'password' => bcrypt('password')]);
        $empEmp->update(['user_id' => $empUser->id, 'manager_id' => $mgrUser->id ?? null]);
        $empUser->roles()->attach(\App\Models\Role::where('slug', 'employee')->first()->id);
    }

    $manager = User::whereHas('roles', fn($q) => $q->where('slug', 'manager'))->first();
    $employee = User::whereHas('roles', fn($q) => $q->where('slug', 'employee'))->first();
    
    // Establish reporting line hierarchy
    $employee->update(['manager_id' => $manager->id]);
    
    // Get first policy ID
    $policyId = \App\Models\TimeOffPolicy::first()?->id ?? 1;
    
    // Create a pending request
    $request = TimeOffRequest::create([
        'user_id' => $employee->id,
        'policy_id' => $policyId,
        'start_date' => now()->addDays(5)->format('Y-m-d'),
        'end_date' => now()->addDays(7)->format('Y-m-d'),
        'days_requested' => 3,
        'status' => 'pending',
        'reason' => 'Test pending request'
    ]);
    
    echo "Created pending time-off request ID: {$request->id}\n";
    
    // Test manager approve
    Auth::setUser($manager);
    $controller = $app->make(\App\Http\Controllers\TimeOffController::class);
    
    // Fake the Request object
    $req = Request::create("/time-off/{$request->id}/approve", 'POST', ['action' => 'approve']);
    $req->setUserResolver(function () use ($manager) { return $manager; });
    $controller->approve($req, $request);
    
    $request->refresh();
    if ($request->status === 'approved') {
        echo "✓ Manager successfully approved time-off request!\n";
    } else {
        echo "X Manager approval failed. Status is {$request->status}\n";
    }

    // 2. Verify Employee profile update form sync
    Auth::setUser($employee);
    $empController = $app->make(\App\Http\Controllers\EmployeeController::class);
    
    $updateReq = Request::create("/employees/{$employee->id}", 'PUT', [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => $employee->email,
        'phone' => '+1234567890'
    ]);
    $updateReq->setUserResolver(function () use ($employee) { return $employee; });
    
    $empController->update($updateReq, $employee);
    
    $employee->refresh();
    $empRecord = \App\Models\Employee::where('user_id', $employee->id)->first();
    if ($employee->first_name === 'Updated' && $empRecord->phone === '+1234567890') {
        echo "✓ Employee profile update synchronized with database successfully!\n";
    } else {
        echo "X Employee profile update failed. Phone is {$empRecord->phone}\n";
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "Done.\n";
