<?php

namespace App\Http\Controllers;

use App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Http\Request;

class OfficeLocationController extends Controller
{
    public function index()
    {
        $locations = OfficeLocation::withCount('employees')->get();
        return view('office-locations.index', compact('locations'));
    }

    public function create()
    {
        return view('office-locations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:50|max:5000',
            'allow_remote' => 'boolean'
        ]);

        $validated['allow_remote'] = $request->has('allow_remote');
        $validated['created_by'] = auth()->id();
        $validated['company_entity_id'] = auth()->user()->company_entity_id;

        OfficeLocation::create($validated);

        return redirect()->route('office-locations.index')->with('success', 'Office location created successfully.');
    }

    public function edit(OfficeLocation $officeLocation)
    {
        return view('office-locations.edit', compact('officeLocation'));
    }

    public function update(Request $request, OfficeLocation $officeLocation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:50|max:5000',
            'allow_remote' => 'boolean'
        ]);

        $validated['allow_remote'] = $request->has('allow_remote');

        $officeLocation->update($validated);

        return redirect()->route('office-locations.index')->with('success', 'Office location updated successfully.');
    }

    public function destroy(OfficeLocation $officeLocation)
    {
        if ($officeLocation->employees()->count() > 0) {
            return back()->with('error', 'Cannot delete office location because employees are currently assigned to it.');
        }

        $officeLocation->delete();

        return redirect()->route('office-locations.index')->with('success', 'Office location deleted successfully.');
    }

    public function assignView()
    {
        // Everyone who can clock in/out is assignable to an office location — including
        // the Super Admin and other admins, not just the "employee" role. Archived
        // (deactivated) accounts are excluded.
        $employees = User::where('account_status', '!=', 'deactivated')
            ->orderBy('first_name')
            ->with(['officeLocations' => function($q) {
                $q->wherePivot('is_primary', true);
            }])
            ->get();
            
        $locations = OfficeLocation::all();
        
        return view('office-locations.assign', compact('employees', 'locations'));
    }

    public function assign(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'office_location_id' => 'required|exists:office_locations,id'
        ]);

        $locationId = $request->office_location_id;

        foreach ($request->user_ids as $userId) {
            $user = User::find($userId);
            
            // Sync without detaching, but set is_primary = true, and update others to false if needed
            // The simplest approach as requested by the spec:
            if (!$user->officeLocations()->where('office_location_id', $locationId)->exists()) {
                $user->officeLocations()->attach($locationId, ['is_primary' => true]);
            }
        }

        return back()->with('success', 'Employees assigned to office location.');
    }

    public function unassign(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'office_location_id' => 'required|exists:office_locations,id'
        ]);

        foreach ($request->user_ids as $userId) {
            $user = User::find($userId);
            $user->officeLocations()->detach($request->office_location_id);
        }

        return back()->with('success', 'Employees unassigned from office location.');
    }
}
