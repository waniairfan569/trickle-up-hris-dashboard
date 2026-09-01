<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Operator-only management of a single company's subscription lifecycle.
 * Gated by the `operator` middleware on the route group.
 */
class SubscriptionController extends Controller
{
    /** Company detail — subscription controls + history. */
    public function show(Tenant $tenant)
    {
        $tenant->admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->first();

        return view('operator.company', [
            'tenant'        => $tenant,
            'plans'         => Plan::active()->ordered()->get(),
            'events'        => $tenant->subscriptionEvents()->with('operator')->limit(30)->get(),
            'featureLabels' => \App\Models\PlanFeature::labels(),
            'symbol'        => config('plans.currency_symbol', '$'),
        ]);
    }

    public function cancel(Tenant $tenant)
    {
        $tenant->update(['status' => 'canceled', 'canceled_at' => now()]);
        SubscriptionEvent::record($tenant, 'canceled', 'Subscription canceled.');

        return back()->with('success', "{$tenant->name}'s subscription canceled.");
    }

    public function reactivate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active', 'canceled_at' => null]);
        SubscriptionEvent::record($tenant, 'reactivated', 'Subscription reactivated.');

        return back()->with('success', "{$tenant->name} reactivated.");
    }

    public function extendTrial(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['days' => 'required|integer|min:1|max:365']);

        // Extend from the current future trial end, else from today.
        $from = ($tenant->trial_ends_at && $tenant->trial_ends_at->isFuture())
            ? $tenant->trial_ends_at->copy()
            : Carbon::now();

        $tenant->update([
            'status'        => 'trialing',
            'trial_ends_at' => $from->addDays((int) $data['days']),
            'canceled_at'   => null,
        ]);
        SubscriptionEvent::record($tenant, 'trial_extended', "Trial set to end {$tenant->trial_ends_at->format('d M Y')} (+{$data['days']}d).");

        return back()->with('success', "Trial extended by {$data['days']} day(s).");
    }

    public function applyDiscount(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['discount_percent' => 'required|integer|min:0|max:100']);
        $pct = (int) $data['discount_percent'];

        $tenant->update(['discount_percent' => $pct ?: null]);
        SubscriptionEvent::record($tenant, 'discount_applied',
            $pct > 0 ? "Discount set to {$pct}%." : 'Discount removed.');

        return back()->with('success', $pct > 0 ? "{$pct}% discount applied." : 'Discount removed.');
    }
}
