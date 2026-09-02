<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile — a lightweight, privacy-friendly captcha for the public
 * signup form. Guarded: when the keys aren't set, enabled() is false and
 * verify() passes, so the form works exactly as before until you turn it on.
 */
class Turnstile
{
    public function enabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /** Verify a widget token with Cloudflare. Passes when disabled. */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                array_filter([
                    'secret' => config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $ip,
                ])
            );

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification error: ' . $e->getMessage());

            // Fail closed only when explicitly enabled — a captcha that can't be
            // checked shouldn't silently wave signups through.
            return false;
        }
    }
}
