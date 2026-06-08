<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

function testRoute($uri, $user, $expectedStatus = 200) {
    global $kernel;
    
    auth()->login($user);
    $request = Request::create($uri, 'GET');
    
    // Add session to request so web middleware works
    $request->setLaravelSession($request->session() ?? session());
    
    $response = $kernel->handle($request);
    
    $status = $response->getStatusCode();
    if ($status === $expectedStatus || ($status === 200 && $expectedStatus === 200)) {
        echo "✓ [$status] GET $uri (as {$user->email}) rendered successfully! (" . strlen($response->getContent()) . " bytes)\n";
    } else {
        echo "X [$status] GET $uri (as {$user->email}) FAILED. Expected $expectedStatus.\n";
        if ($status >= 500) {
            echo substr($response->getContent(), 0, 500) . "\n";
        }
    }
}

try {
    DB::beginTransaction();

    $admin = User::where('email', 'admin@company.com')->first();
    
    // Assign users to existing demo employees
    $mgrEmp = \App\Models\Employee::where('id', 2)->first();
    if ($mgrEmp) {
        if (!$mgrEmp->user_id) {
            $mgrUser = User::create(['company_id' => 1, 'first_name' => $mgrEmp->first_name, 'last_name' => $mgrEmp->last_name, 'email' => $mgrEmp->email, 'password' => bcrypt('password')]);
            $mgrEmp->update(['user_id' => $mgrUser->id]);
        } else {
            $mgrUser = User::find($mgrEmp->user_id);
        }
        if ($mgrUser) {
            $mgrRole = \App\Models\Role::where('slug', 'manager')->first();
            if ($mgrRole && !$mgrUser->roles()->where('slug', 'manager')->exists()) {
                $mgrUser->roles()->attach($mgrRole->id);
            }
        }
    }
    
    $empEmp = \App\Models\Employee::where('id', 3)->first();
    if ($empEmp) {
        if (!$empEmp->user_id) {
            $empUser = User::create(['company_id' => 1, 'first_name' => $empEmp->first_name, 'last_name' => $empEmp->last_name, 'email' => $empEmp->email, 'password' => bcrypt('password')]);
            $empEmp->update(['user_id' => $empUser->id, 'manager_id' => $mgrEmp->id ?? null]);
        } else {
            $empUser = User::find($empEmp->user_id);
        }
        if ($empUser) {
            $empRole = \App\Models\Role::where('slug', 'employee')->first();
            if ($empRole && !$empUser->roles()->where('slug', 'employee')->exists()) {
                $empUser->roles()->attach($empRole->id);
            }
        }
    }

    $manager = User::whereHas('roles', fn($q) => $q->where('slug', 'manager'))->first();
    $employee = User::whereHas('roles', fn($q) => $q->where('slug', 'employee'))->first();

    if (!$admin || !$manager || !$employee) {
        throw new \Exception("Required seeded users not found!");
    }

    echo "========================================================\n";
    echo "        HRIS BLADE VIEWS & UI VERIFICATION SUITE      \n";
    echo "========================================================\n\n";

    function renderBlade($viewName, $data, $user) {
        try {
            \Illuminate\Support\Facades\Auth::setUser($user);
            $html = view($viewName, $data)->render();
            echo "✓ $viewName rendered successfully! (" . strlen($html) . " bytes)\n";
        } catch (\Throwable $e) {
            echo "X Error rendering $viewName: " . $e->getMessage() . "\n";
        }
    }

    echo "1. Testing Admin Dashboards & Views\n";
    renderBlade('dashboard.admin', [], $admin);
    renderBlade('employees.index', ['employees' => \App\Models\Employee::all()], $admin);
    
    // For show employee, we need to pass employee and fields
    $fields = ['email' => 'emp@acme.com', 'job_title' => 'Test', 'salary' => 50000];
    renderBlade('employees.show', ['employee' => $employee, 'fields' => $fields], $admin);

    echo "\n2. Testing Manager Dashboards & Views\n";
    renderBlade('dashboard.manager', [], $manager);
    renderBlade('employees.show', ['employee' => $employee, 'fields' => $fields], $manager);

    echo "\n3. Testing Employee Dashboards & Views\n";
    renderBlade('dashboard.employee', [], $employee);
    renderBlade('employees.show', ['employee' => $employee, 'fields' => $fields], $employee);

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}

echo "\nCompleted visual verification test script.\n";
