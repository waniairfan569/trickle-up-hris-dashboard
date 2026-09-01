<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Http\Request;

/**
 * Owner-only management of the module (feature) catalog that plans are built from.
 * Gated by the `operator.owner` middleware.
 */
class ModulesController extends Controller
{
    public function index()
    {
        $features = PlanFeature::ordered()->get()->map(function ($f) {
            $f->plans_count = $f->plansCount();

            return $f;
        });

        return view('operator.modules', compact('features'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        PlanFeature::create([
            'key'         => PlanFeature::makeKey($data['label']),
            'label'       => $data['label'],
            'description' => $data['description'] ?? null,
            'is_active'   => true,
            'sort_order'  => (int) (PlanFeature::max('sort_order') + 1),
        ]);

        return redirect()->route('operator.modules')->with('success', "Module “{$data['label']}” added.");
    }

    public function update(Request $request, PlanFeature $feature)
    {
        $data = $this->validated($request);

        // Key stays stable (plans reference it); only label/description change.
        $feature->update([
            'label'       => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('operator.modules')->with('success', "Module “{$feature->label}” updated.");
    }

    public function toggleActive(PlanFeature $feature)
    {
        $feature->update(['is_active' => ! $feature->is_active]);

        return redirect()->route('operator.modules')
            ->with('success', "Module “{$feature->label}” " . ($feature->is_active ? 'activated' : 'archived') . '.');
    }

    /** Remove a module and strip its key from every plan that referenced it. */
    public function destroy(PlanFeature $feature)
    {
        $key = $feature->key;

        foreach (Plan::all() as $plan) {
            $feats = $plan->features ?? [];
            if (in_array($key, $feats, true)) {
                $plan->update(['features' => array_values(array_diff($feats, [$key]))]);
            }
        }

        $label = $feature->label;
        $feature->delete();

        return redirect()->route('operator.modules')->with('success', "Module “{$label}” deleted and removed from all plans.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);
    }
}
