<?php

namespace App\Http\Controllers;

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

        $mrr = $tenants->where('status', 'active')->sum(fn ($t) => (float) ($t->planConfig()['price'] ?? 0));

        $stats = [
            'total' => $tenants->count(),
            'active' => $tenants->where('status', 'active')->count(),
            'trialing' => $tenants->where('status', 'trialing')->count(),
            'suspended' => $tenants->where('status', 'suspended')->count(),
            'seats' => $tenants->sum('seat_count'),
            'mrr' => $mrr,
        ];

        return view('operator.index', [
            'tenants' => $tenants,
            'stats' => $stats,
            'symbol' => config('plans.currency_symbol', '$'),
        ]);
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => 'suspended']);

        return back()->with('success', "{$tenant->name} suspended.");
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active']);

        return back()->with('success', "{$tenant->name} activated.");
    }

    public function updatePlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['plan' => 'required|string']);
        abort_unless(config("plans.plans.{$data['plan']}"), 422, 'Unknown plan.');

        $tenant->update(['plan' => $data['plan']]);

        return back()->with('success', "{$tenant->name} moved to the " . config("plans.plans.{$data['plan']}.name") . ' plan.');
    }

    /** Log in as a tenant's admin for support; remembers the operator to return. */
    public function impersonate(Tenant $tenant, Request $request)
    {
        $admin = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'))
            ->first();

        abort_unless($admin, 404, 'That workspace has no admin to impersonate.');

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
