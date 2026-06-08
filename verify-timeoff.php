<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

$admin = App\Models\User::where('email', 'admin@company.com')->first();
Auth::login($admin);

try {
    $req = Request::create('/time-off', 'GET');
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($req);
    echo "Status /time-off: " . $response->getStatusCode() . "\n";
    
    $req2 = Request::create('/time-off/create', 'GET');
    $response2 = $kernel->handle($req2);
    echo "Status /time-off/create: " . $response2->getStatusCode() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
