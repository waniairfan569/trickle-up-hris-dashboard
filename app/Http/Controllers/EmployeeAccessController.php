<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\FeatureCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin: grant individual employees access to specific features (on top of
 * whatever their roles already give). Confidential surfaces (profile info,
 * compensation) are not in the catalog, so they can never be granted here.
 */
class EmployeeAccessController extends Controller
{
    public function edit(User $employee)
    {
        abort_unless(optional(auth()->user())->isAdmin(), 403);

        // Direct per-employee grants (toggleable here).
        $direct = DB::table('feature_user')->where('user_id', $employee->id)
            ->pluck('feature_key')->all();

        // Features the employee already gets through a role — shown read-only so
        // the admin sees the full picture and doesn't think a toggle is "off".
        $roleIds = $employee->roles()->pluck('roles.id');
        $viaRoles = $roleIds->isEmpty() ? [] : DB::table('role_feature')
            ->whereIn('role_id', $roleIds)->pluck('feature_key')->unique()->all();

        // Only offer features the workspace plan actually includes.
        $grouped = [];
        foreach (FeatureCatalog::grouped() as $group => $features) {
            foreach ($features as $key => $label) {
                $plan = FeatureCatalog::planFeature($key);
                if ($plan && !plan_allows($plan)) {
                    continue; // not in this plan — hide to avoid dead grants
                }
                $grouped[$group][$key] = $label;
            }
        }

        return view('employees.access', [
            'employee' => $employee,
            'grouped' => $grouped,
            'direct' => $direct,
            'viaRoles' => $viaRoles,
        ]);
    }

    public function update(Request $request, User $employee)
    {
        abort_unless(optional(auth()->user())->isAdmin(), 403);

        $keys = FeatureCatalog::sanitize((array) $request->input('features', []));
        $now = now();
        $by = auth()->id();

        DB::transaction(function () use ($employee, $keys, $now, $by) {
            DB::table('feature_user')->where('user_id', $employee->id)->delete();
            if ($keys) {
                DB::table('feature_user')->insert(array_map(fn ($k) => [
                    'user_id' => $employee->id,
                    'feature_key' => $k,
                    'granted_by' => $by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $keys));
            }
        });

        return redirect()->route('employees.profile', $employee->id)
            ->with('success', "Feature access updated for {$employee->first_name}.");
    }
}
