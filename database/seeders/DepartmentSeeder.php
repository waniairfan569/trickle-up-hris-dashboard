<?php

namespace Database\Seeders;

use App\Models\CompanyEntity;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Try to get primary entity, or just get any
        $entity = CompanyEntity::primary() ?? CompanyEntity::first();
        $entityId = $entity ? $entity->id : null;
        
        $legacyCompany = \App\Models\Company::first();
        $companyId = $legacyCompany ? $legacyCompany->id : 1;

        // Clean out old departments to avoid duplicates
        Department::query()->forceDelete();

        // Try to find heads based on first name if they exist, otherwise null
        $hamid = User::where('first_name', 'Hamid')->first();
        $dave = User::where('first_name', 'Dave')->first();
        $sara = User::where('first_name', 'Sara')->first();

        // 1. Engineering
        $engineering = Department::create([
            'company_id' => $companyId,
            'company_entity_id' => $entityId,
            'name' => 'Engineering',
            'slug' => Str::slug('Engineering') . '-' . uniqid(),
            'color' => '#3B82F6',
            'head_user_id' => $hamid ? $hamid->id : null,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        // Sub: Frontend
        Department::create([
            'company_id' => $companyId,
            'company_entity_id' => $entityId,
            'name' => 'Frontend',
            'slug' => Str::slug('Frontend') . '-' . uniqid(),
            'parent_id' => $engineering->id,
            'color' => '#3B82F6',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Sub: Backend
        Department::create([
            'company_id' => $companyId,
            'company_entity_id' => $entityId,
            'name' => 'Backend',
            'slug' => Str::slug('Backend') . '-' . uniqid(),
            'parent_id' => $engineering->id,
            'color' => '#3B82F6',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // 2. Sales
        Department::create([
            'company_id' => $companyId,
            'company_entity_id' => $entityId,
            'name' => 'Sales',
            'slug' => Str::slug('Sales') . '-' . uniqid(),
            'color' => '#10B981',
            'head_user_id' => $dave ? $dave->id : null,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        // 3. Human Resources
        Department::create([
            'company_id' => $companyId,
            'company_entity_id' => $entityId,
            'name' => 'Human Resources',
            'slug' => Str::slug('Human Resources') . '-' . uniqid(),
            'color' => '#8B5CF6',
            'head_user_id' => $sara ? $sara->id : null,
            'is_active' => true,
            'sort_order' => 30,
        ]);

        // 4. Finance
        Department::create([
            'company_id' => $companyId,
            'company_entity_id' => $entityId,
            'name' => 'Finance',
            'slug' => Str::slug('Finance') . '-' . uniqid(),
            'color' => '#F59E0B',
            'head_user_id' => null, // no head yet
            'is_active' => true,
            'sort_order' => 40,
        ]);
    }
}
