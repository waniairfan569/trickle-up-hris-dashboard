<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use App\Models\JobLocation;
use Illuminate\Http\Request;

class JobLocationController extends Controller
{
    public function index()
    {
        $locations = JobLocation::withCount('employees')->orderBy('name')->get();

        return view('job-locations.index', compact('locations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:job_locations,name',
            'city'      => 'nullable|string|max:255',
            'country'   => 'nullable|string|size:2',
            'timezone'  => 'nullable|timezone',
            'is_remote' => 'nullable|boolean',
        ]);

        $location = JobLocation::create($this->payload($request, $validated));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'id'           => $location->id,
                'name'         => $location->name,
                'display_name' => $location->display_name,
            ]);
        }

        return back()->with('success', 'Location added.');
    }

    public function update(Request $request, JobLocation $jobLocation)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:job_locations,name,' . $jobLocation->id,
            'city'      => 'nullable|string|max:255',
            'country'   => 'nullable|string|size:2',
            'timezone'  => 'nullable|timezone',
            'is_remote' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $jobLocation->update($this->payload($request, $validated, $jobLocation));

        return back()->with('success', 'Location updated.');
    }

    public function destroy(JobLocation $jobLocation)
    {
        $count = $jobLocation->employees()->count();

        if ($count > 0) {
            return back()->with('error', "Cannot delete — {$count} employees assigned. Reassign them first.");
        }

        $jobLocation->delete();

        return back()->with('success', 'Location deleted.');
    }

    /**
     * Build the attributes array, deriving country_name from the country code.
     */
    private function payload(Request $request, array $validated, ?JobLocation $existing = null): array
    {
        $country = !empty($validated['country']) ? strtoupper($validated['country']) : null;

        return [
            'name'              => $validated['name'],
            'city'              => $validated['city'] ?? null,
            'country'           => $country,
            'country_name'      => $country ? (JobLocation::COUNTRIES[$country] ?? null) : null,
            'timezone'          => $validated['timezone'] ?? null,
            'is_remote'         => $request->boolean('is_remote'),
            'is_active'         => $request->has('is_active')
                ? $request->boolean('is_active')
                : ($existing->is_active ?? true),
            'company_entity_id' => $existing->company_entity_id ?? optional(CompanyEntity::primary())->id,
        ];
    }
}
