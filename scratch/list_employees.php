<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Employee;

echo "EMPLOYEES:\n";
foreach (Employee::all() as $emp) {
    echo "ID: {$emp->id} | Name: {$emp->first_name} {$emp->last_name} | Email: {$emp->email} | User ID: {$emp->user_id}\n";
}

echo "\nUSERS:\n";
foreach (User::with('roles')->get() as $user) {
    $roles = $user->roles->pluck('slug')->implode(', ');
    echo "ID: {$user->id} | Name: {$user->first_name} {$user->last_name} | Email: {$user->email} | Roles: {$roles}\n";
}
