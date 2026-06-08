<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::create('/', 'GET')
);

// Get Super Admin
$admin = App\Models\User::find(1);
if (!$admin) {
    echo "Super admin not found!\n";
    exit(1);
}

// Log in the user
auth()->login($admin);

$routes = app('router')->getRoutes();

$success = 0;
$failed = [];

echo "Testing all GET routes as Super Admin (User ID 1)...\n\n";

foreach ($routes as $route) {
    if (in_array('GET', $route->methods()) && !in_array('api', $route->gatherMiddleware())) {
        $uri = $route->uri();
        
        // Skip routes with parameters for this automated test, or supply dummy values
        if (str_contains($uri, '{')) {
            // Provide a generic ID if possible, e.g., 1 or 11
            $uri = preg_replace('/\{[a-zA-Z0-9_]+\}/', '1', $uri);
        }

        // Create the request
        $testRequest = Illuminate\Http\Request::create('/' . ltrim($uri, '/'), 'GET');
        $testRequest->setSession(session()->driver());
        $testRequest->setUserResolver(function () use ($admin) {
            return $admin;
        });

        try {
            $testResponse = $kernel->handle($testRequest);
            $status = $testResponse->getStatusCode();
            
            if ($status >= 500) {
                $failed[] = [
                    'uri' => $uri,
                    'status' => $status,
                    'error' => $testResponse->exception ? $testResponse->exception->getMessage() : 'Unknown Error'
                ];
                echo "[FAIL] GET /$uri - Status $status\n";
            } else {
                $success++;
                echo "[OK] GET /$uri - Status $status\n";
            }
        } catch (\Exception $e) {
            $failed[] = [
                'uri' => $uri,
                'status' => 500,
                'error' => $e->getMessage()
            ];
            echo "[FAIL] GET /$uri - Exception: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n--- SUMMARY ---\n";
echo "Successful Routes: $success\n";
echo "Failed Routes: " . count($failed) . "\n\n";

if (count($failed) > 0) {
    foreach ($failed as $f) {
        echo "URI: /{$f['uri']}\n";
        echo "Error: {$f['error']}\n\n";
    }
}
