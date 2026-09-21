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

        // Content-Security-Policy — report-only by default (monitors, never blocks);
        // set CSP_ENFORCE=true once violations are clear. Allows the CDNs the app
        // loads (Tailwind Play, unpkg, cdnjs) and inline scripts/styles it relies on.
        if ($this->isHtml($response)) {
            $csp = implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://code.jquery.com https://js.stripe.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com data:",
                "img-src 'self' data: blob: https:",
                "connect-src 'self' https: wss:",
                "frame-src https://js.stripe.com https://hooks.stripe.com https://checkout.stripe.com",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self' https://checkout.stripe.com https://billing.stripe.com",
                "object-src 'none'",
                config('security.csp_report_uri') ? 'report-uri ' . config('security.csp_report_uri') : null,
            ]));

            $headers->set(
                config('security.csp_enforce') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
                $csp
            );
        }

        return $response;
    }

    /** Only attach the CSP to HTML documents (not JSON/PDF/file downloads). */
    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }
}
