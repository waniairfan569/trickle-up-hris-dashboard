<?php
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\TimeOffRequest;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = User::firstOrCreate(
    ['email' => 'admin@company.com'],
    [
        'company_id' => 1,
        'first_name' => 'System',
        'last_name' => 'Admin',
        'password' => Hash::make('password'),
        'status' => 'active',
        'gender' => 'Male'
    ]
);

$superAdminRole = Role::where('slug', 'super_admin')->first();
if ($superAdminRole && !$admin->roles()->where('role_id', $superAdminRole->id)->exists()) {
    $admin->roles()->attach($superAdminRole->id);
}

// Generate a notification for the last time off request
$timeOffRequest = TimeOffRequest::latest()->first();
if ($timeOffRequest && class_exists(\App\Notifications\TimeOffRequestSubmitted::class)) {
    $admin->notify(new \App\Notifications\TimeOffRequestSubmitted($timeOffRequest));
}

echo "Admin user recreated and notification sent.\n";
