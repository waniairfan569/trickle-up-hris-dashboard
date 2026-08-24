<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function index()
    {
        // Load top-level departments with their children recursively
        $departments = Department::topLevel()
            ->with(['head', 'children.head', 'children.children']) // Load a few levels deep
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $totalDepartments = Department::count();
        $totalEmployees = User::whereNotNull('department_id')->count();
        $departmentsWithoutHead = Department::whereNull('head_user_id')->count();

        return view('departments.index', compact('departments', 'totalDepartments', 'totalEmployees', 'departmentsWithoutHead'));
    }

    public function create(Request $request)
    {
        // Any department can be a parent, so sub-departments can nest at any depth.
        $topLevelDepartments = Department::orderBy('name')->get();
        $users = User::orderBy('first_name')->get();
        // Pre-select the parent when adding a sub-department from a department page.
        $preselectParent = $request->integer('parent') ?: null;

        return view('departments.create', compact('topLevelDepartments', 'users', 'preselectParent'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:departments,id',
            'head_user_id' => 'nullable|exists:users,id',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['company_id'] = auth()->user()->company_id ?? 1; // Fallback to 1 if no auth user

        Department::create($validated);

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    public function show(Department $department)
    {
        $department->load(['head', 'children.head']);
        $employees = $department->employees()->orderBy('first_name')->paginate(10);
        
        return view('departments.show', compact('department', 'employees'));
    }

    public function edit(Department $department)
    {
        // Any department can be a parent, except this one and its own
        // descendants (which would create a cycle).
        $excluded = $this->descendantIds($department)->push($department->id)->all();
        $topLevelDepartments = Department::whereNotIn('id', $excluded)->orderBy('name')->get();
        $users = User::orderBy('first_name')->get();

        return view('departments.edit', compact('department', 'topLevelDepartments', 'users'));
    }

    /** IDs of every department nested under the given one (any depth). */
    private function descendantIds(Department $department): \Illuminate\Support\Collection
    {
        $ids = collect();
        $stack = [$department->id];
        while ($stack) {
            $childIds = Department::where('parent_id', array_pop($stack))->pluck('id');
            foreach ($childIds as $cid) {
                if (!$ids->contains($cid)) {
                    $ids->push($cid);
                    $stack[] = $cid;
                }
            }
        }

        return $ids;
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:departments,id',
            'head_user_id' => 'nullable|exists:users,id',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ]);

        if ($validated['parent_id'] == $department->id) {
            return back()->withErrors(['parent_id' => 'A department cannot be its own parent.']);
        }

        if ($validated['name'] !== $department->name) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();
        }

        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $department->update($validated);

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        // Reassign employees to null department first
        $department->employees()->update(['department_id' => null]);
        
        // Handle children: either cascade delete or make them top level. Let's make them top level for safety.
        $department->children()->update(['parent_id' => null]);

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted. Employees were reassigned to no department.');
    }

    public function employees(Department $department)
    {
        // Scoped list of employees for an API or specific view if needed
        $employees = $department->employees()->select('id', 'first_name', 'last_name', 'email', 'avatar_url')->get();
        return response()->json($employees);
    }
}
