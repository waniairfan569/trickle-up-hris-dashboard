<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CompanyEntityController extends Controller
{


    public function index()
    {
        $entities = CompanyEntity::withCount('employees')->orderBy('name')->paginate(10);
        return view('company-entities.index', compact('entities'));
    }

    public function create()
    {
        $timezones = \DateTimeZone::listIdentifiers();
        return view('company-entities.create', compact('timezones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'required|string|max:2',
            'timezone' => 'required|string',
            'currency' => 'required|string|max:3',
            'fiscal_year_start' => 'nullable|string|max:5',
            'work_week_start' => 'required|in:monday,sunday',
            'working_days' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        $validated['created_by'] = auth()->id();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $path;
        }

        // If it's the first entity, make it primary
        if (CompanyEntity::count() === 0) {
            $validated['is_primary'] = true;
        }

        CompanyEntity::create($validated);

        return redirect()->route('company-entities.index')->with('success', 'Company entity created successfully.');
    }

    public function show(CompanyEntity $companyEntity)
    {
        return view('company-entities.show', compact('companyEntity'));
    }

    public function edit(CompanyEntity $companyEntity)
    {
        $timezones = \DateTimeZone::listIdentifiers();
        return view('company-entities.edit', compact('companyEntity', 'timezones'));
    }

    public function update(Request $request, CompanyEntity $companyEntity)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'required|string|max:2',
            'timezone' => 'required|string',
            'currency' => 'required|string|max:3',
            'fiscal_year_start' => 'nullable|string|max:5',
            'work_week_start' => 'required|in:monday,sunday',
            'working_days' => 'nullable|array',
            'is_active' => 'boolean'
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($companyEntity->logo) {
                Storage::disk('public')->delete($companyEntity->logo);
            }
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo'] = $path;
        }

        // Handle checkbox boolean
        $validated['is_active'] = $request->has('is_active');

        $companyEntity->update($validated);

        return redirect()->route('company-entities.index')->with('success', 'Company entity updated successfully.');
    }

    public function setPrimary(CompanyEntity $entity)
    {
        // Unset all
        CompanyEntity::where('is_primary', true)->update(['is_primary' => false]);
        
        // Set this
        $entity->update(['is_primary' => true]);

        return back()->with('success', $entity->name . ' is now the primary company entity.');
    }
}
