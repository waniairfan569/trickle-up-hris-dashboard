<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class AdminRoutesTest extends TestCase
{
    public function test_all_get_routes_do_not_return_500()
    {
        $admin = User::find(1);
        $this->actingAs($admin);

        $routes = Route::getRoutes()->getRoutesByMethod()['GET'];

        $failed = [];
        $success = 0;

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Skip API and authentication routes that aren't relevant for a web admin test
            if (str_contains($uri, 'api/') || str_contains($uri, 'logout') || str_contains($uri, 'sanctum')) {
                continue;
            }

            // Provide dummy IDs for parameters
            if (str_contains($uri, '{')) {
                $uri = preg_replace('/\{[a-zA-Z0-9_]+\}/', '1', $uri);
                // special case for profile which expects something else maybe?
                if (str_contains($uri, '{time_off_policy}')) {
                    $uri = str_replace('{time_off_policy}', '1', $uri);
                }
            }

            // Hit the route
            $response = $this->get('/' . ltrim($uri, '/'));

            if ($response->status() >= 500) {
                $failed[] = [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'error' => $response->exception ? $response->exception->getMessage() : 'Unknown 500'
                ];
            } else {
                $success++;
            }
        }

        if (count($failed) > 0) {
            echo "\n--- FAILED ROUTES ---\n";
            foreach ($failed as $f) {
                echo "GET /{$f['uri']} => Status {$f['status']} | Error: {$f['error']}\n";
            }
        }

        echo "\nTested $success routes successfully.\n";

        $this->assertCount(0, $failed, "There are " . count($failed) . " routes failing with 500 errors.");
    }
}
