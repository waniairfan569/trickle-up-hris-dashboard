<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Services\StripeBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Stripe webhooks — the source of truth for subscription state. The
 * signature is verified with the webhook secret; the parsed event is then
 * dispatched by process(), which is kept separate so it can be unit-tested
 * without a signature.
 *
 * Route is CSRF-exempt and unauthenticated (Stripe posts server-to-server).
 */
class StripeWebhookController extends Controller
{
    public function __construct(private StripeBilling $stripe)
    {
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $secret = config('services.stripe.webhook_secret');

        if ($this->stripe->isConfigured() && filled($secret)) {
            try {
                $event = $this->stripe->verifyWebhook($payload, $request->header('Stripe-Signature'));
            } catch (\Throwable $e) {
                Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());

                return response('Invalid signature', 400);
            }
        } elseif (app()->environment('local')) {
            // Local dev without a secret (e.g. replaying a saved payload).
            $event = json_decode($payload, true) ?: [];
        } else {
            return response('Webhook not configured', 400);
        }

        try {
            $this->process($event);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing error: ' . $e->getMessage());

            return response('Processing error', 500);
        }

        return response('ok', 200);
    }

    /** Dispatch a parsed Stripe event to the right state transition. */
    public function process(array $event): void
    {
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                $tenant = $this->tenantForEvent($object);
                if ($tenant) {
                    $planKey = $object['metadata']['plan_key'] ?? $tenant->plan;
                    $tenant->markActive($planKey, $object['subscription'] ?? null, $object['customer'] ?? null);
                    SubscriptionEvent::record($tenant, 'activated', 'Subscription activated (Stripe Checkout completed).');
                }
                break;

            case 'customer.subscription.updated':
                $tenant = $this->tenantForEvent($object);
                if ($tenant) {
                    $status = $object['status'] ?? '';
                    if (in_array($status, ['active', 'trialing'], true)) {
                        $tenant->markActive($tenant->plan, $object['id'] ?? null, $object['customer'] ?? null);
                        SubscriptionEvent::record($tenant, 'reactivated', 'Subscription is active.');
                    } elseif (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
                        $tenant->markCanceled();
                        SubscriptionEvent::record($tenant, 'canceled', "Subscription {$status}.");
                    } elseif ($status === 'past_due') {
                        SubscriptionEvent::record($tenant, 'payment_failed', 'Subscription past due — payment needed.');
                    }
                }
                break;

            case 'customer.subscription.deleted':
                $tenant = $this->tenantForEvent($object);
                if ($tenant) {
                    $tenant->markCanceled();
                    SubscriptionEvent::record($tenant, 'canceled', 'Subscription canceled.');
                }
                break;

            case 'invoice.payment_failed':
                $tenant = $this->tenantForEvent($object);
                if ($tenant) {
                    SubscriptionEvent::record($tenant, 'payment_failed', 'A subscription payment failed.');
                }
                break;

            case 'invoice.paid':
                $tenant = $this->tenantForEvent($object);
                if ($tenant && $tenant->status !== 'active') {
                    $tenant->markActive($tenant->plan, $tenant->stripe_subscription_id, $object['customer'] ?? null);
                    SubscriptionEvent::record($tenant, 'activated', 'Invoice paid — subscription active.');
                }
                break;

            default:
                // Unhandled event types are acknowledged (200) and ignored.
                break;
        }
    }

    /**
     * Resolve the workspace an event belongs to: prefer explicit metadata, then
     * the subscription id, then the Stripe customer id.
     */
    private function tenantForEvent(array $object): ?Tenant
    {
        if ($id = ($object['metadata']['tenant_id'] ?? null)) {
            if ($t = Tenant::find($id)) {
                return $t;
            }
        }

        // Subscription objects: their own id is the subscription id.
        if (($object['object'] ?? null) === 'subscription' && ($object['id'] ?? null)) {
            if ($t = Tenant::where('stripe_subscription_id', $object['id'])->first()) {
                return $t;
            }
        }

        if ($subId = ($object['subscription'] ?? null)) {
            if ($t = Tenant::where('stripe_subscription_id', $subId)->first()) {
                return $t;
            }
        }

        if ($custId = ($object['customer'] ?? null)) {
            if ($t = Tenant::where('stripe_customer_id', $custId)->first()) {
                return $t;
            }
        }

        return null;
    }
}
