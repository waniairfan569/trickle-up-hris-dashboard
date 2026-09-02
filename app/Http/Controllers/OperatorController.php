<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OperatorController extends Controller
{
    /** Platform overview: all agencies + headline stats. */
    public function index()
    {
        $tenants = Tenant::orderByDesc('created_at')->get()->map(function ($t) {
            $t->seat_count = $t->seatCount();
            $t->admin = User::withoutGlobalScopes()
                ->where('tenant_id', $t->id)
                ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
                ->first();

            return $t;
        });

        $stats = [
            'total' => $tenants->count(),
            'active' => $tenants->where('status', 'active')->count(),
            'trialing' => $tenants->where('status', 'trialing')->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
            'seats' => $tenants->sum('seat_count'),
            'mrr' => $tenants->sum(fn ($t) => $t->mrr()), // discount-aware, active only
        ];

        return view('operator.index', [
            'tenants' => $tenants,
            'stats' => $stats,
            'plans' => Plan::active()->ordered()->get(),
            'symbol' => config('plans.currency_symbol', '$'),
        ]);
    }

    /** Billing dashboard — revenue, subscription health, and recent activity. */
    public function billing()
    {
        $tenants = Tenant::all();

        $stats = [
            'mrr'       => round($tenants->sum(fn ($t) => $t->mrr()), 2),
            'arr'       => round($tenants->sum(fn ($t) => $t->mrr()) * 12, 2),
            'active'    => $tenants->where('status', 'active')->count(),
            'trialing'  => $tenants->where('status', 'trialing')->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
            'canceled'  => $tenants->where('status', 'canceled')->count(),
            'discounted'=> $tenants->filter(fn ($t) => (int) $t->discount_percent > 0)->count(),
        ];

        // Revenue by plan (active tenants only).
        $byPlan = $tenants->where('status', 'active')
            ->groupBy(fn ($t) => $t->planKey())
            ->map(fn ($grp, $key) => [
                'name'  => optional(Plan::forKey($key))->name ?? ucfirst($key),
                'count' => $grp->count(),
                'mrr'   => round($grp->sum(fn ($t) => $t->mrr()), 2),
            ])->sortByDesc('mrr')->values();

        // Trials ending within 7 days.
        $trialsEnding = $tenants->filter(fn ($t) => $t->onTrial() && $t->trialDaysLeft() <= 7)
            ->sortBy(fn ($t) => $t->trial_ends_at)->values();

        $recent = SubscriptionEvent::with(['tenant', 'operator'])->latest()->limit(15)->get();

        return view('operator.billing', [
            'stats'        => $stats,
            'byPlan'       => $byPlan,
            'trialsEnding' => $trialsEnding,
            'recent'       => $recent,
            'symbol'       => config('plans.currency_symbol', '$'),
        ]);
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => 'suspended']);
        SubscriptionEvent::record($tenant, 'suspended', 'Workspace suspended.');

        return back()->with('success', "{$tenant->name} suspended.");
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active', 'canceled_at' => null]);
        SubscriptionEvent::record($tenant, 'activated', 'Workspace activated.');

        return back()->with('success', "{$tenant->name} activated.");
    }

    public function updatePlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['plan' => 'required|string']);
        $plan = Plan::forKey($data['plan']);
        abort_unless($plan, 422, 'Unknown plan.');

        $tenant->update(['plan' => $plan->key]);
        SubscriptionEvent::record($tenant, 'plan_changed', "Plan changed to {$plan->name}.");

        return back()->with('success', "{$tenant->name} moved to the {$plan->name} plan.");
    }

    /** GDPR data portability — download a workspace's full data as JSON. */
    public function exportWorkspace(Tenant $tenant, \App\Services\WorkspaceExporter $exporter)
    {
        \App\Models\OperatorAudit::record('workspace_exported', "Exported all data for {$tenant->name}.", $tenant->id);

        $json = $exporter->toJson($tenant);
        $filename = 'workspace-' . $tenant->slug . '-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /** GDPR right-to-erasure — permanently delete a workspace and all its data. */
    public function destroyWorkspace(Request $request, Tenant $tenant, \App\Services\WorkspaceExporter $exporter)
    {
        abort_if($tenant->slug === 'trickle-up', 403, 'The primary workspace cannot be deleted.');

        $request->validate(['confirm_slug' => 'required|string']);
        if (trim($request->confirm_slug) !== $tenant->slug) {
            return back()->withErrors(['confirm_slug' => 'The typed slug does not match. Deletion canceled.']);
        }

        // Keep a backup export + an audit trail (both survive the deletion).
        \Illuminate\Support\Facades\Storage::disk('local')
            ->put('exports/purged-' . $tenant->slug . '-' . now()->format('Y-m-d_His') . '.json', $exporter->toJson($tenant));

        $name = $tenant->name;
        \App\Models\OperatorAudit::record('workspace_purged', "Permanently deleted workspace {$name} and all its data.", null);

        $deleted = $exporter->purge($tenant);

        return redirect()->route('operator.index')
            ->with('success', "Workspace “{$name}” permanently deleted (" . array_sum($deleted) . ' records).');
    }

    /** Log in as a tenant's admin for support; remembers the operator to return. */
    public function impersonate(Tenant $tenant, Request $request)
    {
        $admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->first();

        abort_unless($admin, 404, 'That workspace has no admin to impersonate.');

        \App\Models\OperatorAudit::record('impersonate', "Impersonated {$tenant->name} as {$admin->full_name}.", $tenant->id);

        $request->session()->put('operator_impersonator_id', Auth::id());
        Auth::login($admin);

        return redirect('/dashboard')->with('success', "You are now viewing {$tenant->name} as {$admin->full_name}.");
    }

    /** Return to the operator account after impersonating. */
    public function stopImpersonating(Request $request)
    {
        $operatorId = $request->session()->pull('operator_impersonator_id');
        abort_unless($operatorId, 403);

        $operator = User::withoutGlobalScopes()->find($operatorId);
        abort_unless($operator && $operator->isOperator(), 403);

        Auth::login($operator);

        return redirect()->route('operator.index')->with('success', 'Back to the operator console.');
    }
}
