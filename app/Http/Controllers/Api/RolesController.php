<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::where('company_id', $request->user()->company_id)
            ->withCount('users')
            ->get();
            
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'type'        => 'nullable|in:admin,employee',
            'permissions' => 'nullable|array',
        ]);

        $role = Role::create([
            'company_id'  => $request->user()->company_id,
            'name'        => $request->name,
            'type'        => $request->type ?? 'employee',
            'permissions' => $request->permissions ?? [],
        ]);

        return response()->json($role, 201);
    }

    public function show(Request $request, $id)
    {
        $role = Role::where('company_id', $request->user()->company_id)->findOrFail($id);
        return response()->json($role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::where('company_id', $request->user()->company_id)->findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'type'        => 'sometimes|in:admin,employee',
            'permissions' => 'nullable|array',
        ]);

        if ($request->has('name'))        $role->name        = $request->name;
        if ($request->has('type'))        $role->type        = $request->type;
        if ($request->has('permissions')) $role->permissions = $request->permissions;
        
        $role->save();

        return response()->json($role);
    }

    public function destroy(Request $request, $id)
    {
        $role = Role::where('company_id', $request->user()->company_id)->findOrFail($id);
        
        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Cannot delete role that is assigned to users.'], 400);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
