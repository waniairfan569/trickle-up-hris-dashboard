<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrisRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a parent company required by foreign keys in some models
        $this->company = Company::create([
            'name' => 'Acme Corp',
        ]);
    }

    /**
     * Test Role model constants and relationships.
     */
    public function test_role_model_properties_and_relations(): void
    {
        // 1. Constants check
        $this->assertEquals('super_admin', Role::SUPER_ADMIN);
        $this->assertEquals('hr_admin', Role::HR_ADMIN);
        $this->assertEquals('manager', Role::MANAGER);
        $this->assertEquals('employee', Role::EMPLOYEE);
        $this->assertEquals('restricted', Role::RESTRICTED);

        // 2. Create roles and permissions
        $role = Role::create([
            'name' => 'HR Manager',
            'slug' => Role::HR_ADMIN,
            'description' => 'Manages HR operations',
            'is_system' => true,
        ]);

        $permission = Permission::create([
            'name' => 'View Employees',
            'slug' => 'employees.view',
            'module' => 'employees',
            'description' => 'Can view employee list',
        ]);

        // Connect Role <-> Permission (pivot)
        $role->permissions()->attach($permission->id);

        $this->assertTrue($role->permissions->contains($permission));
        $this->assertTrue($permission->roles->contains($role));
    }

    /**
     * Test Permission model query scope.
     */
    public function test_permission_module_scope(): void
    {
        Permission::create([
            'name' => 'View Employees',
            'slug' => 'employees.view',
            'module' => 'employees',
        ]);

        Permission::create([
            'name' => 'Create Timeoff',
            'slug' => 'timeoff.create',
            'module' => 'time_off',
        ]);

        $employeesPermissions = Permission::forModule('employees')->get();
        $timeoffPermissions = Permission::forModule('time_off')->get();

        $this->assertCount(1, $employeesPermissions);
        $this->assertEquals('employees.view', $employeesPermissions->first()->slug);

        $this->assertCount(1, $timeoffPermissions);
        $this->assertEquals('timeoff.create', $timeoffPermissions->first()->slug);
    }

    /**
     * Test Department self-referencing relationships and user relationship.
     */
    public function test_department_parent_child_and_user_relations(): void
    {
        $parentDept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
        ]);

        $childDept = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Backend Team',
            'parent_id' => $parentDept->id,
        ]);

        // Check self-referencing relations
        $this->assertEquals($parentDept->id, $childDept->parent->id);
        $this->assertTrue($parentDept->children->contains($childDept));

        // Create a user in the child department
        $user = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@acme.com',
            'password' => bcrypt('password'),
            'department_id' => $childDept->id,
        ]);

        $this->assertEquals($childDept->id, $user->department->id);
        $this->assertTrue($childDept->users->contains($user));
    }

    /**
     * Test RoleChecker and User helper methods.
     */
    public function test_role_and_permission_checking_on_user(): void
    {
        // 1. Seed Roles and Permissions
        $adminRole = Role::create([
            'name' => 'Super Administrator',
            'slug' => Role::SUPER_ADMIN,
        ]);

        $empRole = Role::create([
            'name' => 'Employee',
            'slug' => Role::EMPLOYEE,
        ]);

        $viewPerm = Permission::create([
            'name' => 'View Self',
            'slug' => 'self.view',
            'module' => 'profile',
        ]);

        $managePerm = Permission::create([
            'name' => 'Manage System',
            'slug' => 'system.manage',
            'module' => 'system',
        ]);

        $adminRole->permissions()->attach($managePerm->id);
        $empRole->permissions()->attach($viewPerm->id);

        // 2. Create Users
        $adminUser = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@acme.com',
            'password' => bcrypt('password'),
        ]);
        $adminUser->roles()->attach($adminRole->id, [
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        $empUser = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Employee',
            'last_name' => 'User',
            'email' => 'emp@acme.com',
            'password' => bcrypt('password'),
        ]);
        $empUser->roles()->attach($empRole->id, [
            'assigned_by' => $adminUser->id,
            'assigned_at' => now(),
        ]);

        // 3. Test hasRole
        $this->assertTrue($adminUser->hasRole(Role::SUPER_ADMIN));
        $this->assertFalse($adminUser->hasRole(Role::EMPLOYEE));
        $this->assertTrue($empUser->hasRole(Role::EMPLOYEE));
        $this->assertTrue($empUser->hasRole([Role::EMPLOYEE, Role::SUPER_ADMIN])); // array check

        // 4. Test hasPermission
        $this->assertTrue($adminUser->hasPermission('system.manage'));
        $this->assertFalse($adminUser->hasPermission('self.view'));
        $this->assertTrue($empUser->hasPermission('self.view'));
        $this->assertFalse($empUser->hasPermission('system.manage'));

        // 5. Test isAdmin
        $this->assertTrue($adminUser->isAdmin());
        $this->assertFalse($empUser->isAdmin());
    }

    /**
     * Test User manager/directReports self-referencing relationship.
     */
    public function test_user_management_hierarchy_and_reporting_lines(): void
    {
        $manager = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Alice',
            'last_name' => 'Manager',
            'email' => 'alice@acme.com',
            'password' => bcrypt('password'),
        ]);

        $emp1 = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Bob',
            'last_name' => 'Developer',
            'email' => 'bob@acme.com',
            'password' => bcrypt('password'),
            'manager_id' => $manager->id,
        ]);

        $emp2 = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Charlie',
            'last_name' => 'Junior',
            'email' => 'charlie@acme.com',
            'password' => bcrypt('password'),
            'manager_id' => $emp1->id,
        ]);

        // check immediate relationships
        $this->assertEquals($manager->id, $emp1->manager->id);
        $this->assertTrue($manager->directReports->contains($emp1));

        // check indirect / recursive canManage
        $this->assertTrue($manager->canManage($emp1));
        $this->assertTrue($manager->canManage($emp2)); // Charlie reports to Bob, who reports to Alice
        $this->assertTrue($emp1->canManage($emp2));

        $this->assertFalse($emp2->canManage($manager));
        $this->assertFalse($manager->canManage($manager)); // self check should return false
    }

    /**
     * Test dynamic isManager, getAccessScope and User metadata config.
     */
    public function test_user_access_scopes_and_casts(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => Role::SUPER_ADMIN]);
        $managerRole = Role::create(['name' => 'Manager', 'slug' => Role::MANAGER]);
        $empRole = Role::create(['name' => 'Employee', 'slug' => Role::EMPLOYEE]);

        // 1. Admin user
        $admin = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@acme.com',
            'password' => bcrypt('password'),
        ]);
        $admin->roles()->attach($adminRole->id);

        // 2. Manager user (assigned Role::MANAGER but no reports yet)
        $manager = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Manager',
            'last_name' => 'User',
            'email' => 'manager@acme.com',
            'password' => bcrypt('password'),
        ]);
        $manager->roles()->attach($managerRole->id);

        // 3. Team Lead user (no Manager role, but has active direct reports)
        $lead = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Lead',
            'last_name' => 'User',
            'email' => 'lead@acme.com',
            'password' => bcrypt('password'),
        ]);
        $lead->roles()->attach($empRole->id);

        // 4. Employee user (reports to Lead)
        $employee = User::create([
            'company_id' => $this->company->id,
            'first_name' => 'Employee',
            'last_name' => 'User',
            'email' => 'employee@acme.com',
            'password' => bcrypt('password'),
            'manager_id' => $lead->id,
        ]);
        $employee->roles()->attach($empRole->id);

        // Verify isManager
        $this->assertTrue($manager->isManager()); // True because of role 'manager'
        $this->assertTrue($lead->isManager());    // True because has direct reports (employee)
        $this->assertFalse($employee->isManager()); // False (no reports, no role)

        // Verify access scopes
        $this->assertEquals('all', $admin->getAccessScope());
        $this->assertEquals('department', $manager->getAccessScope());
        $this->assertEquals('team', $lead->getAccessScope());
        $this->assertEquals('self', $employee->getAccessScope());

        // Verify Casts, Fillable and Hidden on User
        $userModel = new User();
        $this->assertContains('department_id', $userModel->getFillable());
        $this->assertContains('employee_status', $userModel->getFillable());
        $this->assertContains('joined_at', $userModel->getFillable());

        $this->assertEquals('string', $userModel->getCasts()['employee_status']);
        $this->assertEquals('date', $userModel->getCasts()['joined_at']);

        $this->assertContains('password', $userModel->getHidden());
        $this->assertContains('salary', $userModel->getHidden());
    }
}
