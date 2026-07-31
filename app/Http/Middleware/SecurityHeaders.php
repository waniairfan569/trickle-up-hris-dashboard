<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers on every web response:
 * clickjacking protection, MIME-sniffing protection, referrer/permissions
 * policy, and HSTS over HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        // Clickjacking: don't allow the app to be framed by other sites.
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Stop browsers from MIME-sniffing a response away from the declared type.
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Limit how much referrer info leaks to other origins.
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Lock down powerful browser features we don't use.
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=()');

        // HSTS — only advertise over a genuinely secure connection.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
