<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

/**
 * Operator-only management of subscription plans (dynamic, DB-backed).
 * Gated by the `operator` middleware on the route group.
 */
class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withTrashed()->ordered()->get()->map(function ($p) {
            $p->tenants_count = $p->tenantsCount();

            return $p;
        });

        return view('operator.plans', [
            'plans'         => $plans,
            'featureLabels' => config('plans.feature_labels', []),
            'currency'      => config('plans.currency', 'USD'),
            'symbol'        => config('plans.currency_symbol', '$'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Plan::create([
            'key'             => Plan::makeKey($data['name']),
            'name'            => $data['name'],
            'price'           => $data['price'],
            'currency'        => $data['currency'],
            'interval'        => $data['interval'],
            'seats'           => $data['seats'],
            'features'        => $this->features($request),
            'trial_days'      => $data['trial_days'] ?? 0,
            'blurb'           => $data['blurb'] ?? null,
            'is_public'       => $request->boolean('is_public'),
            'is_active'       => true,
            'sort_order'      => (int) (Plan::max('sort_order') + 1),
            'stripe_price_id' => $data['stripe_price_id'] ?? null,
        ]);

        return redirect()->route('operator.plans')->with('success', "Plan “{$data['name']}” created.");
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validated($request);

        // The `key` is the stable identifier tenants store — never change it.
        $plan->update([
            'name'            => $data['name'],
            'price'           => $data['price'],
            'currency'        => $data['currency'],
            'interval'        => $data['interval'],
            'seats'           => $data['seats'],
            'features'        => $this->features($request),
            'trial_days'      => $data['trial_days'] ?? 0,
            'blurb'           => $data['blurb'] ?? null,
            'is_public'       => $request->boolean('is_public'),
            'stripe_price_id' => $data['stripe_price_id'] ?? null,
        ]);

        return redirect()->route('operator.plans')->with('success', "Plan “{$plan->name}” updated.");
    }

    /** Archive / un-archive — hides a plan from new selection; existing tenants keep it. */
    public function toggleActive(Plan $plan)
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return redirect()->route('operator.plans')
            ->with('success', "Plan “{$plan->name}” " . ($plan->is_active ? 'activated' : 'archived') . '.');
    }

    public function duplicate(Plan $plan)
    {
        $copy = $plan->replicate(['created_at', 'updated_at', 'deleted_at']);
        $copy->name = $plan->name . ' (copy)';
        $copy->key = Plan::makeKey($copy->name);
        $copy->is_public = false;
        $copy->is_active = true;
        $copy->sort_order = (int) (Plan::max('sort_order') + 1);
        $copy->save();

        return redirect()->route('operator.plans')->with('success', "Duplicated “{$plan->name}”.");
    }

    /** Delete — blocked while any company is on the plan (archive it instead). */
    public function destroy(Plan $plan)
    {
        if ($plan->tenantsCount() > 0) {
            return redirect()->route('operator.plans')
                ->with('error', "Can’t delete “{$plan->name}” — {$plan->tenantsCount()} company(ies) are on it. Archive it instead.");
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()->route('operator.plans')->with('success', "Plan “{$name}” deleted.");
    }

    // ---- Internals ----------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:100',
            'price'           => 'required|numeric|min:0|max:1000000',
            'currency'        => 'required|string|max:8',
            'interval'        => 'required|in:monthly,yearly',
            'seats'           => 'required|integer|min:0|max:1000000',
            'trial_days'      => 'nullable|integer|min:0|max:365',
            'blurb'           => 'nullable|string|max:255',
            'is_public'       => 'nullable|boolean',
            'all_features'    => 'nullable|boolean',
            'features'        => 'nullable|array',
            'features.*'      => 'string',
            'stripe_price_id' => 'nullable|string|max:255',
        ]);
    }

    /** ['*'] when "all features" is ticked, else the selected feature keys. */
    private function features(Request $request): array
    {
        return $request->boolean('all_features')
            ? ['*']
            : array_values($request->input('features', []));
    }
}
