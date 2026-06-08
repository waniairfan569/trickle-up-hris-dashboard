<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\PerformanceReview;

$admin = App\Models\User::where('email', 'admin@company.com')->first();
Auth::login($admin);

try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    // 1. Check Index
    $req = Request::create('/performance', 'GET');
    $response = $kernel->handle($req);
    echo "Status /performance: " . $response->getStatusCode() . "\n";
    
    // 2. Check Show
    $review = PerformanceReview::first();
    if ($review) {
        $req2 = Request::create("/performance/{$review->id}", 'GET');
        $response2 = $kernel->handle($req2);
        echo "Status /performance/{id}: " . $response2->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
