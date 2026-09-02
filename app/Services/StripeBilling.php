<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;

/**
 * Thin wrapper around the Stripe API for workspace (tenant) subscriptions.
 *
 * Every entry point is guarded by isConfigured(): until the Stripe SDK is
 * installed (`composer require stripe/stripe-php`) AND a secret key is set, the
 * service reports "not configured" and callers fall back to instant plan
 * activation — so the app runs unchanged with no Stripe account. Once keys are
 * present it creates real Checkout / Billing-Portal sessions and verifies
 * webhooks. No Stripe class is referenced until after the guard passes.
 */
class StripeBilling
{
    /** Ready to talk to Stripe? (SDK installed + secret key present.) */
    public function isConfigured(): bool
    {
        return class_exists(\Stripe\StripeClient::class)
            && filled(config('services.stripe.secret'));
    }

    private function client(): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient(config('services.stripe.secret'));
    }

    /**
     * A hosted Checkout Session URL for the tenant to subscribe to $plan.
     * Returns null when Stripe isn't configured or the plan has no price id.
     */
    public function checkoutUrl(Tenant $tenant, Plan $plan, User $user, string $successUrl, string $cancelUrl): ?string
    {
        if (!$this->isConfigured() || blank($plan->stripe_price_id)) {
            return null;
        }

        $customerId = $this->ensureCustomer($tenant, $user);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [[
                'price' => $plan->stripe_price_id,
                'quantity' => 1,
            ]],
            'client_reference_id' => (string) $tenant->id,
            'metadata' => ['tenant_id' => $tenant->id, 'plan_key' => $plan->key],
            'subscription_data' => [
                'metadata' => ['tenant_id' => $tenant->id, 'plan_key' => $plan->key],
            ],
            'allow_promotion_codes' => true,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return $session->url;
    }

    /** A Stripe Billing-Portal URL so an existing customer can manage/cancel. */
    public function billingPortalUrl(Tenant $tenant, string $returnUrl): ?string
    {
        if (!$this->isConfigured() || blank($tenant->stripe_customer_id)) {
            return null;
        }

        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $tenant->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    /** Retrieve a completed Checkout Session as an array (for the return page). */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId);

        return json_decode(json_encode($session), true);
    }

    /** Verify a webhook payload and return the parsed event as an array. */
    public function verifyWebhook(string $payload, ?string $signature): array
    {
        $secret = config('services.stripe.webhook_secret');

        $event = \Stripe\Webhook::constructEvent($payload, (string) $signature, $secret);

        return json_decode(json_encode($event), true);
    }

    /** Find (or create + persist) the Stripe customer for a workspace. */
    private function ensureCustomer(Tenant $tenant, User $user): string
    {
        if (filled($tenant->stripe_customer_id)) {
            return $tenant->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'name' => $tenant->name,
            'email' => $user->email,
            'metadata' => ['tenant_id' => $tenant->id],
        ]);

        $tenant->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }
}
