<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force HTTPS for the whole site EXCEPT the ZKTeco /iclock endpoints, which the
 * devices can only reach over plain HTTP.
 *
 * Controlled by config('app.force_https') (env FORCE_HTTPS) so it can be turned
 * on only on the live server, and switched off instantly if anything misbehaves.
 * Requires TrustProxies (configured in bootstrap/app.php) so $request->secure()
 * reflects the real scheme behind Plesk's nginx proxy — otherwise it would loop.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            config('app.force_https')
            && !$request->secure()
            && !$request->is('iclock', 'iclock/*')
        ) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
