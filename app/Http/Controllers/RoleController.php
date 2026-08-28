<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->get();

        return view('roles.index', compact('roles'));
    }

    /** Read-only preview of a role's granted permissions, grouped by module. */
    public function show(Role $role)
    {
        $this->authorizeManage();

        $modules = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $granted = $role->slug === Role::SUPER_ADMIN
            ? Permission::pluck('id')->all()               // super admin implicitly has all
            : $role->permissions->pluck('id')->all();

        return view('roles.show', compact('role', 'modules', 'granted'));
    }

    public function create()
    {
        $this->authorizeManage();

        $modules = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');

        return view('roles.create', ['role' => new Role, 'modules' => $modules, 'granted' => []]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => 'required|string|max:60',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('roles.index')->with('success', "Role “{$role->name}” created.");
    }

    public function edit(Role $role)
    {
        $this->authorizeManage();

        $modules = Permission::orderBy('module')->orderBy('name')->get()->groupBy('module');
        $granted = $role->slug === Role::SUPER_ADMIN
            ? Permission::pluck('id')->all()
            : $role->permissions->pluck('id')->all();

        return view('roles.edit', compact('role', 'modules', 'granted'));
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeManage();

        $data = $request->validate([
            // System roles keep their name/slug; only custom roles can be renamed.
            'name' => [$role->is_system ? 'nullable' : 'required', 'string', 'max:60'],
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if (!$role->is_system && !empty($data['name'])) {
            if ($data['name'] !== $role->name) {
                $role->slug = $this->uniqueSlug($data['name'], $role->id);
            }
            $role->name = $data['name'];
        }
        $role->description = $data['description'] ?? $role->description;
        $role->save();

        // Super Admin always has every permission (enforced in code) — don't let
        // the editor lock it out of anything.
        if ($role->slug !== Role::SUPER_ADMIN) {
            $role->permissions()->sync($data['permissions'] ?? []);
        }

        return redirect()->route('roles.index')->with('success', "Permissions for “{$role->name}” updated.");
    }

    public function destroy(Role $role)
    {
        $this->authorizeManage();

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'Cannot delete a role while users are assigned to it. Reassign them first.');
        }

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('success', "Role “{$name}” deleted.");
    }

    // ---------------------------------------------------------------------

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $slug = $base;
        $i = 1;
        while (Role::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '_' . (++$i);
        }

        return $slug;
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(Role::SUPER_ADMIN), 403, 'Only a super admin can manage roles and permissions.');
    }
}
