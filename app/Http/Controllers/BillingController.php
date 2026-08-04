<?php

namespace App\Http\Controllers;

use App\Tenancy\TenantManager;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function index(TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404, 'No active workspace.');

        return view('billing.index', [
            'tenant' => $tenant,
            'plans' => config('plans.plans'),
            'featureLabels' => config('plans.feature_labels'),
            'symbol' => config('plans.currency_symbol', '$'),
        ]);
    }

    /**
     * Choose a plan. This is the Stripe integration point: once Cashier is
     * installed and STRIPE keys are set, redirect to a Cashier Checkout session
     * for $plan['stripe_price'] and only activate the plan on the webhook.
     *
     * Until then it records the selected plan and activates the workspace so the
     * rest of the billing framework (limits, feature gates) is fully testable.
     */
    public function subscribe(Request $request, TenantManager $tenants)
    {
        $tenant = $this->resolveTenant($tenants);
        abort_unless($tenant, 404);

        $validated = $request->validate(['plan' => 'required|string']);
        $key = $validated['plan'];
        $plan = config("plans.plans.$key");

        abort_unless($plan && ($plan['selectable'] ?? false), 422, 'Unknown plan.');

        // === STRIPE CHECKOUT GOES HERE (Cashier) ===
        // return $tenant->newSubscription('default', $plan['stripe_price'])
        //     ->checkout([...success/cancel URLs...]);

        $tenant->update(['plan' => $key, 'status' => 'active']);

        return redirect()->route('billing.index')
            ->with('success', "You're now on the {$plan['name']} plan. (Payment integration is pending — connect Stripe to charge.)");
    }
}
