<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Services\StripeBilling;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function index(TenantManager $tenants, StripeBilling $stripe)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404, 'No active workspace.');

        // Dynamic, DB-backed plans — shaped like the old config array so the view
        // is unchanged. Only public + active plans are offered to customers.
        $plans = Plan::public()->ordered()->get()
            ->mapWithKeys(fn ($p) => [$p->key => $p->toConfigArray()]);

        return view('billing.index', [
            'tenant' => $tenant,
            'plans' => $plans,
            'featureLabels' => \App\Models\PlanFeature::labels(),
            'symbol' => config('plans.currency_symbol', '$'),
            'stripeReady' => $stripe->isConfigured(),
        ]);
    }

    /**
     * Choose a plan. When Stripe is configured and the plan has a price id, the
     * customer is sent to a hosted Checkout session and the plan is only
     * activated once payment is confirmed (checkout success + webhook). Without
     * Stripe it falls back to instant activation so limits and feature gates
     * stay fully testable.
     */
    public function subscribe(Request $request, TenantManager $tenants, StripeBilling $stripe)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $validated = $request->validate(['plan' => 'required|string']);
        $plan = Plan::forKey($validated['plan']);

        abort_unless($plan && $plan->is_public && $plan->is_active, 422, 'Unknown plan.');

        // Real card payment via Stripe Checkout.
        if ($stripe->isConfigured() && filled($plan->stripe_price_id)) {
            try {
                $url = $stripe->checkoutUrl(
                    $tenant,
                    $plan,
                    $request->user(),
                    route('billing.success') . '?session_id={CHECKOUT_SESSION_ID}',
                    route('billing.cancel'),
                );

                if ($url) {
                    return redirect()->away($url);
                }
            } catch (\Throwable $e) {
                Log::error('Stripe checkout failed: ' . $e->getMessage());

                return redirect()->route('billing.index')
                    ->with('error', 'We could not start checkout. Please try again or contact support.');
            }
        }

        // Fallback: no Stripe (or plan has no price id) — activate immediately.
        $tenant->markActive($plan->key);
        SubscriptionEvent::record($tenant, 'plan_changed', "Switched to the {$plan->name} plan.");

        $note = $stripe->isConfigured()
            ? "You're now on the {$plan->name} plan."
            : "You're now on the {$plan->name} plan. (Connect Stripe to charge cards.)";

        return redirect()->route('billing.index')->with('success', $note);
    }

    /** Return URL from Stripe Checkout — best-effort immediate activation. */
    public function success(Request $request, TenantManager $tenants, StripeBilling $stripe)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $sessionId = $request->query('session_id');

        if ($stripe->isConfigured() && $sessionId) {
            try {
                $session = $stripe->retrieveCheckoutSession($sessionId);
                if (($session['payment_status'] ?? null) === 'paid') {
                    $planKey = $session['metadata']['plan_key'] ?? $tenant->plan;
                    $tenant->markActive($planKey, $session['subscription'] ?? null, $session['customer'] ?? null);
                    SubscriptionEvent::record($tenant, 'activated', 'Subscription activated via Stripe Checkout.');
                }
            } catch (\Throwable $e) {
                Log::warning('Stripe success fast-path failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('billing.index')
            ->with('success', 'Payment received — your plan is active. Thank you!');
    }

    /** Customer backed out of Checkout. */
    public function cancel()
    {
        return redirect()->route('billing.index')
            ->with('error', 'Checkout canceled — no charge was made.');
    }

    /** Send an existing customer to the Stripe Billing Portal to manage/cancel. */
    public function portal(TenantManager $tenants, StripeBilling $stripe)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $url = $stripe->billingPortalUrl($tenant, route('billing.index'));

        if (!$url) {
            return redirect()->route('billing.index')
                ->with('error', 'No active subscription to manage yet.');
        }

        return redirect()->away($url);
    }
}
